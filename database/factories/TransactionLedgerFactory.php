<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionLedger;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionLedger>
 */
class TransactionLedgerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalance = fake()->randomFloat(2, 0, 1_000_000);
        $amount = fake()->randomFloat(2, -500_000, 500_000);

        return [
            'wallet_id' => Wallet::factory(),
            'transaction_id' => Transaction::factory(),
            'transaction_type' => TransactionType::Expense,
            'opening_balance' => $openingBalance,
            'amount' => $amount,
            'closing_balance' => $openingBalance + $amount,
        ];
    }
}
