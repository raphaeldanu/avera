<?php

namespace App\Filament\Widgets;

use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Balance', $this->getBalance()),
            Stat::make('Monthly Income', $this->getMonthlyIncome()),
            Stat::make('Monthly Expense', $this->getMonthlyExpense()),
        ];
    }

    protected function getBalance(): string
    {
        $totalBalance = Wallet::where('user_id', Auth::id())->sum('current_balance');

        return Number::currency($totalBalance, 'IDR', 'id', 2);
    }

    protected function getMonthlyIncome(): string
    {
        $totalBalance = Wallet::where('user_id', Auth::id())->sum('current_balance');

        return Number::currency($totalBalance, 'IDR', 'id', 2);
    }

    protected function getMonthlyExpense(): string
    {
        $totalBalance = Wallet::where('user_id', Auth::id())->sum('current_balance');

        return Number::currency($totalBalance, 'IDR', 'id', 2);
    }
}
