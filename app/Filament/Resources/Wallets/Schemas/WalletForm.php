<?php

namespace App\Filament\Resources\Wallets\Schemas;

use App\Enums\WalletType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class WalletForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(WalletType::class)
                    ->required()
                    ->native(false),
                TextInput::make('initial_balance')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(','),
            ]);
    }
}
