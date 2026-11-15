<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_category_id' => TransactionCategory::factory(),
            'wallet_id' => Wallet::factory(),
            'type' => TransactionType::Expense,
            'transaction_date' => now()->toDateString(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 1, 1_000_000),
        ];
    }
}
