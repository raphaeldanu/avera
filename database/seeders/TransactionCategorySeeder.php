<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Offerings',
            'Utilities',
            'Subscription',
            'Entertainment',
            'Groceries',
            'Health',
            'Transport',
            'Savings',
        ];

        foreach($categories as $category) {
            TransactionCategory::create(['name' => $category]);
        }
    }
}
