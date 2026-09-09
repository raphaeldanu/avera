<?php

namespace App\Filament\Resources\Wallets\Pages;

use App\Filament\Resources\Wallets\WalletResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWallet extends CreateRecord
{
    protected static string $resource = WalletResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['current_balance'] = $data['initial_balance'];

        return $data;
    }
}
