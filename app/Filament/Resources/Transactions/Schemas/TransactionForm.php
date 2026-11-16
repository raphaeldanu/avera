<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionEntry;
use App\Models\Wallet;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                    ->closeOnDateSelection()
                    ->placeholder(now()->toDateString())
                    ->defaultFocusedDate(now()->toDateString()),
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
                static::withBalanceDisplay(Select::make('wallet_id'))
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
                    ->rules([static::walletBalanceRule(TransactionType::Expense)])
                    ->native(false)
                    ->preload()
                    ->searchable(),
                static::walletBalanceInput('wallet_id'),
                static::withBalanceDisplay(Select::make('transfer_from_wallet_id'))
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
                    ->rules([static::walletBalanceRule(TransactionType::Transfer)])
                    ->different('transfer_to_wallet_id')
                    ->native(false)
                    ->searchable(),
                static::walletBalanceInput('transfer_from_wallet_id'),
                static::withBalanceDisplay(Select::make('transfer_to_wallet_id'))
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
                static::walletBalanceInput('transfer_to_wallet_id'),
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

    /**
     * Determine whether debiting the wallet would make any running balance negative.
     */
    public static function walletBalanceError(
        int $walletId,
        string $amount,
        string $transactionDate,
        ?Transaction $record = null,
    ): ?string {
        $wallet = Wallet::query()->find($walletId);

        if (! $wallet) {
            return null;
        }

        $entries = TransactionEntry::query()
            ->where('wallet_id', $walletId)
            ->when($record, fn (Builder $query) => $query->where('transaction_id', '!=', $record->id))
            ->with('transaction:id,transaction_date')
            ->get()
            ->filter(fn (TransactionEntry $entry): bool => $entry->transaction !== null)
            ->map(fn (TransactionEntry $entry): array => [
                'transaction_date' => $entry->transaction->transaction_date,
                'transaction_id' => $entry->transaction_id,
                'entry_id' => $entry->id,
                'amount' => $entry->amount,
            ]);

        $entries->push([
            'transaction_date' => $transactionDate,
            'transaction_id' => $record?->id ?? PHP_INT_MAX,
            'entry_id' => PHP_INT_MAX,
            'amount' => bcmul($amount, '-1', 2),
        ]);

        $balance = $wallet->initial_balance;

        foreach ($entries->sortBy([
            fn (array $first, array $second): int => $first['transaction_date'] <=> $second['transaction_date'],
            fn (array $first, array $second): int => $first['transaction_id'] <=> $second['transaction_id'],
            fn (array $first, array $second): int => $first['entry_id'] <=> $second['entry_id'],
        ]) as $entry) {
            $balance = bcadd($balance, $entry['amount'], 2);

            if (bccomp($balance, '0', 2) < 0) {
                return 'The wallet balance is not enough to cover this transaction.';
            }
        }

        return null;
    }

    private static function walletBalanceRule(TransactionType $debitedType): Closure
    {
        return fn (Get $get, ?Transaction $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $debitedType): void {
            if (static::parseType($get('type')) !== $debitedType) {
                return;
            }

            $amount = $get('amount');
            $transactionDate = $get('transaction_date');

            if (! filled($value) || ! is_numeric($amount) || blank($transactionDate)) {
                return;
            }

            $message = static::walletBalanceError(
                walletId: (int) $value,
                amount: (string) $amount,
                transactionDate: Carbon::parse($transactionDate)->toDateString(),
                record: $record,
            );

            if ($message !== null) {
                $fail($message);
            }
        };
    }

    private static function withBalanceDisplay(Select $select): Select
    {
        return $select
            ->live()
            ->afterStateUpdated(function (Set $set, ?string $state) use ($select): void {
                $set("{$select->getName()}_balance", static::formatBalance($state));
            });
    }

    private static function walletBalanceInput(string $walletField): TextInput
    {
        return TextInput::make("{$walletField}_balance")
            ->label('Current balance')
            ->prefix('Rp')
            ->disabled()
            ->dehydrated(false)
            ->visible(fn (Get $get): bool => filled($get($walletField)))
            ->formatStateUsing(fn (Get $get): ?string => static::formatBalance($get($walletField)));
    }

    private static function formatBalance(mixed $walletId): ?string
    {
        if (! is_numeric($walletId)) {
            return null;
        }

        $balance = Wallet::query()
            ->where('user_id', Auth::id())
            ->find((int) $walletId)?->current_balance;

        return $balance === null ? null : number_format((float) $balance, 0, ',', '.');
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
