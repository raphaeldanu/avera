<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'wallet_id',
    'transaction_id',
    'transaction_type',
    'opening_balance',
    'amount',
    'closing_balance',
])]
class TransactionLedger extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'opening_balance' => 'decimal:2',
            'amount' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Rebuild the running balance history for the given wallets.
     *
     * @param  array<int, int|string>  $walletIds
     */
    public static function rebuildForWallets(array $walletIds): void
    {
        foreach (array_unique($walletIds) as $walletId) {
            $wallet = Wallet::query()->find($walletId);

            if (! $wallet) {
                continue;
            }

            static::rebuildForWallet($wallet);
        }
    }

    /**
     * Rebuild every ledger row of a wallet in chronological order and sync its current balance.
     */
    public static function rebuildForWallet(Wallet $wallet): void
    {
        DB::transaction(function () use ($wallet): void {
            static::query()->where('wallet_id', $wallet->id)->delete();

            $balance = $wallet->initial_balance;

            $entries = TransactionEntry::query()
                ->where('wallet_id', $wallet->id)
                ->with('transaction')
                ->get()
                ->filter(fn (TransactionEntry $entry): bool => $entry->transaction !== null)
                ->sortBy([
                    fn (TransactionEntry $first, TransactionEntry $second): int => $first->transaction->transaction_date <=> $second->transaction->transaction_date,
                    fn (TransactionEntry $first, TransactionEntry $second): int => $first->transaction->id <=> $second->transaction->id,
                    fn (TransactionEntry $first, TransactionEntry $second): int => $first->id <=> $second->id,
                ]);

            foreach ($entries as $entry) {
                $openingBalance = $balance;
                $balance = bcadd($balance, $entry->amount, 2);

                static::query()->create([
                    'wallet_id' => $wallet->id,
                    'transaction_id' => $entry->transaction_id,
                    'transaction_type' => $entry->transaction->type,
                    'opening_balance' => $openingBalance,
                    'amount' => $entry->amount,
                    'closing_balance' => $balance,
                ]);
            }

            $wallet->update(['current_balance' => $balance]);
        });
    }
}
