<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionLedger;
use App\Models\User;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function balanceFormData(TransactionCategory $category, array $attributes = []): array
{
    return [
        'title' => 'Balance transaction',
        'transaction_date' => now()->toDateString(),
        'transaction_category_id' => $category->id,
        'type' => TransactionType::Expense->value,
        'amount' => 100000,
        ...$attributes,
    ];
}

it('rejects an expense that exceeds the wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 150000,
        ]))
        ->call('create')
        ->assertHasFormErrors(['wallet_id']);

    expect(Transaction::query()->count())->toBe(0)
        ->and(TransactionLedger::query()->count())->toBe(0)
        ->and($wallet->refresh()->current_balance)->toBe('100000.00');
});

it('allows an expense that uses the entire wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TransactionLedger::query()->sole()->closing_balance)->toBe('0.00')
        ->and($wallet->refresh()->current_balance)->toBe('0.00');
});

it('rejects a transfer that exceeds the source wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $sourceWallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);
    $destinationWallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'type' => TransactionType::Transfer->value,
            'wallet_id' => null,
            'transfer_from_wallet_id' => $sourceWallet->id,
            'transfer_to_wallet_id' => $destinationWallet->id,
            'amount' => 150000,
        ]))
        ->call('create')
        ->assertHasFormErrors(['transfer_from_wallet_id']);

    expect(Transaction::query()->count())->toBe(0)
        ->and($sourceWallet->refresh()->current_balance)->toBe('100000.00')
        ->and($destinationWallet->refresh()->current_balance)->toBe('0.00');
});

it('rejects a backdated expense that would make an earlier running balance negative', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create();

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'type' => TransactionType::Income->value,
            'wallet_id' => $wallet->id,
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $wallet->id,
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 50000,
        ]))
        ->call('create')
        ->assertHasFormErrors(['wallet_id']);

    expect(Transaction::query()->count())->toBe(1)
        ->and($wallet->refresh()->current_balance)->toBe('100000.00');
});

it('rejects editing an expense beyond the wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 60000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->fillForm(['amount' => 110000])
        ->call('save')
        ->assertHasFormErrors(['wallet_id']);

    expect($transaction->refresh()->amount)->toBe('60000.00')
        ->and(TransactionLedger::query()->sole()->closing_balance)->toBe('40000.00')
        ->and($wallet->refresh()->current_balance)->toBe('40000.00');
});

it('allows editing an expense up to the wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 60000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(EditTransaction::class, ['record' => Transaction::query()->sole()->getRouteKey()])
        ->fillForm(['amount' => 100000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(TransactionLedger::query()->sole()->closing_balance)->toBe('0.00')
        ->and($wallet->refresh()->current_balance)->toBe('0.00');
});

it('rejects moving an expense to a wallet with an insufficient balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $originalWallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 100000,
        'current_balance' => 100000,
    ]);
    $newWallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 50000,
        'current_balance' => 50000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(balanceFormData($category, [
            'wallet_id' => $originalWallet->id,
            'amount' => 80000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->fillForm(['wallet_id' => $newWallet->id])
        ->call('save')
        ->assertHasFormErrors(['wallet_id']);

    expect($transaction->refresh()->wallet_id)->toBe($originalWallet->id)
        ->and($originalWallet->refresh()->current_balance)->toBe('20000.00')
        ->and($newWallet->refresh()->current_balance)->toBe('50000.00');
});
