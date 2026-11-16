<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected int|string|null $transferFromWalletId = null;

    protected int|string|null $transferToWalletId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->transferFromWalletId = $data['transfer_from_wallet_id'] ?? null;
        $this->transferToWalletId = $data['transfer_to_wallet_id'] ?? null;

        unset($data['transfer_from_wallet_id'], $data['transfer_to_wallet_id']);

        $data['user_id'] = Auth::id();

        return TransactionForm::normalizeWalletId($data);
    }

    protected function afterCreate(): void
    {
        $this->record->generateLedger(
            transferFromWalletId: $this->transferFromWalletId,
            transferToWalletId: $this->transferToWalletId,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
