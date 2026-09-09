<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WalletType: string implements HasLabel
{
    case CASH = 'cash';
    case BANK_ACCOUNT = 'bank_account';
    case E_WALLET = 'e_wallet';
    case INVESTMENT_ACCOUNT = 'investment_account';

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BANK_ACCOUNT => 'Bank Account',
            self::E_WALLET => 'E-Wallet',
            self::INVESTMENT_ACCOUNT => 'Investment Account',
        };
    }
}
