<?php

namespace App\Models;

use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'type',
    'initial_balance',
    'current_balance',
    'is_active'
])]
class Wallet extends Model
{
    protected function casts(): array
    {
        return [
            'type' => WalletType::class,
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
}
