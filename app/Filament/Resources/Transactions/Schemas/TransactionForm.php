<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
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
                        if (! static::isIncomeOrExpense($state)) {
                            $set('wallet_id', null);
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
        $type = $data['type'] ?? null;
        $type = $type instanceof TransactionType
            ? $type
            : TransactionType::tryFrom((string) $type);

        if (! in_array($type, [TransactionType::Income, TransactionType::Expense], true)) {
            $data['wallet_id'] = null;
        }

        return $data;
    }

    private static function isIncomeOrExpense(mixed $type): bool
    {
        $type = $type instanceof TransactionType
            ? $type
            : TransactionType::tryFrom((string) $type);

        return in_array($type, [
            TransactionType::Income,
            TransactionType::Expense,
        ], true);
    }
}
