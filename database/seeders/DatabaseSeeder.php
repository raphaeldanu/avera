<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use App\Models\User;
use Database\Seeders\TransactionCategorySeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::create([
            'name' => 'Raphael Adhimas',
            'email' => 'raphaeldanu@gmail.com',
            'password' => Hash::make('raphaeldanu@gmail.com'),
        ]);

        $categories = [
            'Offerings',
            'Utilities',
            'Subscription',
            'Entertainment',
            'Groceries',
            'Health',
            'Transport',
            'Savings',
            'My Future Wife',
            'Other',
        ];

        foreach($categories as $category) {
            TransactionCategory::create([
                'user_id' => $user->id,
                'name' => $category,
            ]);
        }
    }
}
