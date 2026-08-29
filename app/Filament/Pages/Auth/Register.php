<?php
namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

class Register extends BaseRegister
{
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        // Create the user using the standard parent method
        $user = parent::handleRegistration($data);

        // Generate related data immediately after user creation
        $user->transactionCategories()->createMany([
            ['name' => 'Philanthropy'],
            ['name' => 'Utilities'],
            ['name' => 'Subscription'],
            ['name' => 'Entertainment'],
            ['name' => 'Groceries'],
            ['name' => 'Health'],
            ['name' => 'Transport'],
            ['name' => 'Savings'],
            ['name' => 'Other'],
        ]);

        return $user;
    }
}
