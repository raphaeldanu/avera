<?php

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'type',
    'initial_balance',
    'current_balance',
    'is_active',
])]
class Wallet extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactionEntries(): HasMany
    {
        return $this->hasMany(TransactionEntry::class);
    }

    public function transactionLedgers(): HasMany
    {
        return $this->hasMany(TransactionLedger::class);
    }
}
