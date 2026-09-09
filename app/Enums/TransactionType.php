<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TransactionType: string implements HasLabel
{
    case Income     = 'income';
    case Expense    = 'expense';
    case Transfer   = 'transfer';

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
        };
    }
}
