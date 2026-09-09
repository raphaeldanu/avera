<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\Wallet;
use Filament\Forms\Components\DatePicker;
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
                Select::make('wallet_id')
                    ->label('Wallet')
                    ->options(fn () => Wallet::query()
                        ->where('user_id', Auth::id())
                        ->where('is_active', true)
                        ->pluck('name', 'id')
                    )
                    ->required()
                    ->searchable()
                    ->visible(fn (Get $get) =>
                        in_array($get('type'), [
                            TransactionType::Income,
                            TransactionType::Expense,
                        ])
                    )
                    ->live(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(','),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
