<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\Wallet;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                DatePicker::make('transaction_date')
                    ->required()
                    ->native(false)
                    ->defaultFocusedDate(now()->startOfMonth()),
                Select::make('transaction_category_id')
                    ->relationship(
                        name: 'transactionCategory',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', Auth::id())
                    )
                    ->required()
                    ->native(false)
                    ->preload()
                    ->searchable(),
                Select::make('type')
                    ->options(TransactionType::class)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (static::isTransfer($state)) {
                            $set('wallet_id', null);
                        } else {
                            $set('transfer_from_wallet_id', null);
                            $set('transfer_to_wallet_id', null);
                        }
                    }),
                Select::make('wallet_id')
                    ->label('Wallet')
                    ->relationship(
                        name: 'wallet',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('user_id', Auth::id())
                            ->where('is_active', true),
                    )
                    ->required(fn (Get $get): bool => static::isIncomeOrExpense($get('type')))
                    ->visible(fn (Get $get): bool => static::isIncomeOrExpense($get('type')))
                    ->native(false)
                    ->preload()
                    ->searchable(),
                Select::make('transfer_from_wallet_id')
                    ->label('From wallet')
                    ->options(fn (): array => static::walletOptions())
                    ->scopedExists(
                        model: Wallet::class,
                        column: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('user_id', Auth::id())
                            ->where('is_active', true),
                    )
                    ->required(fn (Get $get): bool => static::isTransfer($get('type')))
                    ->visible(fn (Get $get): bool => static::isTransfer($get('type')))
                    ->different('transfer_to_wallet_id')
                    ->native(false)
                    ->searchable(),
                Select::make('transfer_to_wallet_id')
                    ->label('To wallet')
                    ->options(fn (): array => static::walletOptions())
                    ->scopedExists(
                        model: Wallet::class,
                        column: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('user_id', Auth::id())
                            ->where('is_active', true),
                    )
                    ->required(fn (Get $get): bool => static::isTransfer($get('type')))
                    ->visible(fn (Get $get): bool => static::isTransfer($get('type')))
                    ->different('transfer_from_wallet_id')
                    ->native(false)
                    ->searchable(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->validationMessages([
                        'min' => 'The transaction amount must be greater than zero.',
                    ]),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeWalletId(array $data): array
    {
        $type = static::parseType($data['type'] ?? null);

        if (! in_array($type, [TransactionType::Income, TransactionType::Expense], true)) {
            $data['wallet_id'] = null;
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    public static function walletOptions(): array
    {
        return Wallet::query()
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function isIncomeOrExpense(mixed $type): bool
    {
        return in_array(static::parseType($type), [
            TransactionType::Income,
            TransactionType::Expense,
        ], true);
    }

    private static function isTransfer(mixed $type): bool
    {
        return static::parseType($type) === TransactionType::Transfer;
    }

    private static function parseType(mixed $type): ?TransactionType
    {
        return $type instanceof TransactionType
            ? $type
            : TransactionType::tryFrom((string) $type);
    }
}
