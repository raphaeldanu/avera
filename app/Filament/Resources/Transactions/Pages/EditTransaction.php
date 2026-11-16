<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected int|string|null $transferFromWalletId = null;

    protected int|string|null $transferToWalletId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['transfer_from_wallet_id'] = null;
        $data['transfer_to_wallet_id'] = null;

        if ($this->record->type === TransactionType::Transfer) {
            $data['transfer_from_wallet_id'] = $this->record->transactionEntries()
                ->where('amount', '<', 0)
                ->value('wallet_id');

            $data['transfer_to_wallet_id'] = $this->record->transactionEntries()
                ->where('amount', '>', 0)
                ->value('wallet_id');
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->transferFromWalletId = $data['transfer_from_wallet_id'] ?? null;
        $this->transferToWalletId = $data['transfer_to_wallet_id'] ?? null;

        unset($data['transfer_from_wallet_id'], $data['transfer_to_wallet_id']);

        return TransactionForm::normalizeWalletId($data);
    }

    protected function afterSave(): void
    {
        $this->record->generateLedger(
            transferFromWalletId: $this->transferFromWalletId,
            transferToWalletId: $this->transferToWalletId,
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
