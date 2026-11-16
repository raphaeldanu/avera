<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function transactionResourceFormData(TransactionCategory $category, Wallet $wallet): array
{
    return [
        'title' => 'Weekly groceries',
        'transaction_date' => now()->toDateString(),
        'transaction_category_id' => $category->id,
        'type' => TransactionType::Expense->value,
        'wallet_id' => $wallet->id,
        'amount' => 100000,
    ];
}

it('creates an expense transaction with one wallet', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm(transactionResourceFormData($category, $wallet))
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->wallet_id)->toBe($wallet->id)
        ->and($transaction->type)->toBe(TransactionType::Expense)
        ->and($transaction->amount)->toBe('100000.00');
});

it('requires a wallet for income and expense transactions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...transactionResourceFormData($category, $wallet),
            'wallet_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['wallet_id' => 'required']);

    expect(Transaction::query()->count())->toBe(0);
});

it('creates a transfer transaction with two wallet entries', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();
    $destinationWallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...transactionResourceFormData($category, $wallet),
            'type' => TransactionType::Transfer->value,
            'wallet_id' => null,
            'transfer_from_wallet_id' => $wallet->id,
            'transfer_to_wallet_id' => $destinationWallet->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();

    expect($transaction->wallet_id)->toBeNull()
        ->and($transaction->type)->toBe(TransactionType::Transfer)
        ->and($transaction->transactionEntries()->count())->toBe(2);
});

it('requires both transfer wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...transactionResourceFormData($category, $wallet),
            'type' => TransactionType::Transfer->value,
            'wallet_id' => null,
            'transfer_from_wallet_id' => null,
            'transfer_to_wallet_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'transfer_from_wallet_id' => 'required',
            'transfer_to_wallet_id' => 'required',
        ]);

    expect(Transaction::query()->count())->toBe(0);
});

it('requires a positive transaction amount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...transactionResourceFormData($category, $wallet),
            'amount' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['amount' => 'min']);

    expect(Transaction::query()->count())->toBe(0);
});

it('updates the wallet when editing a transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $originalWallet = Wallet::factory()->for($user)->create();
    $newWallet = Wallet::factory()->for($user)->create();

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'transaction_category_id' => $category->id,
        'wallet_id' => $originalWallet->id,
        'type' => TransactionType::Expense,
        'amount' => 50000,
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->assertFormSet(['wallet_id' => $originalWallet->id])
        ->fillForm([
            'wallet_id' => $newWallet->id,
            'amount' => 75000,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $transaction->refresh();

    expect($transaction->wallet_id)->toBe($newWallet->id)
        ->and($transaction->amount)->toBe('75000.00');
});

it('clears the wallet when changing an expense to a transfer', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();
    $destinationWallet = Wallet::factory()->for($user)->create();

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'transaction_category_id' => $category->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 50000,
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->fillForm([
            'type' => TransactionType::Transfer->value,
            'transfer_from_wallet_id' => $wallet->id,
            'transfer_to_wallet_id' => $destinationWallet->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($transaction->refresh()->wallet_id)->toBeNull()
        ->and($transaction->type)->toBe(TransactionType::Transfer)
        ->and($transaction->transactionEntries()->count())->toBe(2);
});

it('shows the transaction wallet in the transactions table', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create(['name' => 'Everyday Wallet']);

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'transaction_category_id' => $category->id,
        'wallet_id' => $wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 100000,
    ]);

    Livewire::test(ListTransactions::class)
        ->assertCanSeeTableRecords([$transaction])
        ->assertSee('Everyday Wallet');
});
