<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'user_id',
    'transaction_category_id',
    'wallet_id',
    'type',
    'transaction_date',
    'title',
    'description',
    'amount',
])]
class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => TransactionType::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (self $transaction): void {
            $walletIds = $transaction->transactionEntries()
                ->pluck('wallet_id')
                ->push($transaction->wallet_id)
                ->filter()
                ->all();

            $transaction->transactionEntries()->delete();

            TransactionLedger::rebuildForWallets($walletIds);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactionEntries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class, 'transaction_id');
    }

    public function transactionLedgers(): HasMany
    {
        return $this->hasMany(TransactionLedger::class, 'transaction_id');
    }

    /**
     * Regenerate this transaction's wallet entries and running balance history.
     */
    public function generateLedger(?int $transferFromWalletId = null, ?int $transferToWalletId = null): void
    {
        $affectedWalletIds = $this->transactionEntries()->pluck('wallet_id')->all();

        $entries = match ($this->type) {
            TransactionType::Income => [
                ['wallet_id' => $this->wallet_id, 'amount' => $this->amount],
            ],
            TransactionType::Expense => [
                ['wallet_id' => $this->wallet_id, 'amount' => bcmul($this->amount, '-1', 2)],
            ],
            TransactionType::Transfer => [
                ['wallet_id' => $transferFromWalletId, 'amount' => bcmul($this->amount, '-1', 2)],
                ['wallet_id' => $transferToWalletId, 'amount' => $this->amount],
            ],
        };

        $entries = array_filter(
            $entries,
            fn (array $entry): bool => $entry['wallet_id'] !== null,
        );

        DB::transaction(function () use ($entries, $affectedWalletIds): void {
            $this->transactionEntries()->delete();

            foreach ($entries as $entry) {
                $this->transactionEntries()->create($entry);
                $affectedWalletIds[] = $entry['wallet_id'];
            }

            TransactionLedger::rebuildForWallets($affectedWalletIds);
        });
    }
}
