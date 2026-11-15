<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\Wallet;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->rule(fn (Get $get) => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        static::validateAllocationsTotal($get, $value, $fail);
                    })
                    ->validationMessages([
                        'min' => 'The transaction amount must be greater than zero.',
                    ]),
                Repeater::make('transactionEntries')
                    ->relationship('transactionEntries')
                    ->label('Wallet Allocation')
                    ->helperText('Split this transaction across one or more wallets. The allocations must add up to the transaction amount.')
                    ->schema([
                        Select::make('wallet_id')
                            ->label('Wallet')
                            ->options(fn () => Wallet::query()
                                ->where('user_id', Auth::id())
                                ->where('is_active', true)
                                ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->distinct()
                            ->scopedExists(
                                model: Wallet::class,
                                column: 'id',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->where('user_id', Auth::id())
                                    ->where('is_active', true),
                            ),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->validationMessages([
                                'min' => 'Each wallet allocation must be greater than zero.',
                            ]),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Add wallet allocation')
                    ->rule(fn (Get $get) => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        static::validateAllocationsTotal($get, $get('amount'), $fail);
                    })
                    ->validationMessages([
                        'min' => 'At least one wallet allocation is required.',
                    ]),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    private static function validateAllocationsTotal(Get $get, mixed $value, Closure $fail): void
    {
        $entries = $get('transactionEntries');

        if (! is_array($entries) || $entries === []) {
            return;
        }

        $allocatedTotal = collect($entries)
            ->sum(fn (mixed $entry): float => (float) data_get($entry, 'amount', 0));

        if (round($allocatedTotal, 2) !== round((float) $value, 2)) {
            $fail('The wallet allocations must add up to the transaction amount.');
        }
    }
}
