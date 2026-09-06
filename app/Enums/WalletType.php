<?php

namespace App\Enums;

enum WalletType: string
{
    case CASH = 'cash';
    case BANK_ACCOUNT = 'bank_account';
    case E_WALLET = 'e_wallet';
    case INVESTMENT_ACCOUNT = 'investment_account';
}
