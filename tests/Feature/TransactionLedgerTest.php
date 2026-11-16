<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionLedger;
use App\Models\User;
use App\Models\Wallet;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function ledgerFormData(TransactionCategory $category, array $attributes = []): array
{
    return [
        'title' => 'Ledger transaction',
        'transaction_date' => now()->toDateString(),
        'transaction_category_id' => $category->id,
        'type' => TransactionType::Expense->value,
        'amount' => 100000,
        ...$attributes,
    ];
}

it('records an income as a single ledger row and increases the wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 500000,
        'current_balance' => 500000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'type' => TransactionType::Income->value,
            'wallet_id' => $wallet->id,
            'amount' => 150000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $ledger = TransactionLedger::query()->sole();

    expect($ledger->wallet_id)->toBe($wallet->id)
        ->and($ledger->transaction_id)->toBe(Transaction::query()->sole()->id)
        ->and($ledger->transaction_type)->toBe(TransactionType::Income)
        ->and($ledger->opening_balance)->toBe('500000.00')
        ->and($ledger->amount)->toBe('150000.00')
        ->and($ledger->closing_balance)->toBe('650000.00')
        ->and($wallet->refresh()->current_balance)->toBe('650000.00');
});

it('records an expense as a single negative ledger row and decreases the wallet balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 500000,
        'current_balance' => 500000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $ledger = TransactionLedger::query()->sole();

    expect($ledger->amount)->toBe('-100000.00')
        ->and($ledger->closing_balance)->toBe('400000.00')
        ->and($wallet->refresh()->current_balance)->toBe('400000.00');
});

it('records a transfer as two ledger rows across both wallets', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $sourceWallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 1000000,
        'current_balance' => 1000000,
    ]);
    $destinationWallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 200000,
        'current_balance' => 200000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'type' => TransactionType::Transfer->value,
            'wallet_id' => null,
            'transfer_from_wallet_id' => $sourceWallet->id,
            'transfer_to_wallet_id' => $destinationWallet->id,
            'amount' => 250000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();
    $ledgers = TransactionLedger::query()->orderBy('id')->get();

    expect($transaction->wallet_id)->toBeNull()
        ->and($transaction->transactionEntries()->count())->toBe(2)
        ->and($ledgers)->toHaveCount(2)
        ->and($ledgers[0]->wallet_id)->toBe($sourceWallet->id)
        ->and($ledgers[0]->amount)->toBe('-250000.00')
        ->and($ledgers[0]->closing_balance)->toBe('750000.00')
        ->and($ledgers[1]->wallet_id)->toBe($destinationWallet->id)
        ->and($ledgers[1]->amount)->toBe('250000.00')
        ->and($ledgers[1]->closing_balance)->toBe('450000.00')
        ->and($sourceWallet->refresh()->current_balance)->toBe('750000.00')
        ->and($destinationWallet->refresh()->current_balance)->toBe('450000.00');
});

it('recalculates the ledger when a transaction is edited', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 500000,
        'current_balance' => 500000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(EditTransaction::class, ['record' => Transaction::query()->sole()->getRouteKey()])
        ->fillForm(['amount' => 150000])
        ->call('save')
        ->assertHasNoFormErrors();

    $ledger = TransactionLedger::query()->sole();

    expect($ledger->amount)->toBe('-150000.00')
        ->and($ledger->opening_balance)->toBe('500000.00')
        ->and($ledger->closing_balance)->toBe('350000.00')
        ->and($wallet->refresh()->current_balance)->toBe('350000.00');
});

it('removes the ledger and restores the wallet balance when a transaction is deleted', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 500000,
        'current_balance' => 500000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'wallet_id' => $wallet->id,
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(EditTransaction::class, ['record' => Transaction::query()->sole()->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(TransactionLedger::query()->count())->toBe(0)
        ->and($wallet->refresh()->current_balance)->toBe('500000.00');
});

it('lays out the running balance in transaction date order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $category = TransactionCategory::factory()->for($user)->create();
    $wallet = Wallet::factory()->for($user)->create([
        'initial_balance' => 1000000,
        'current_balance' => 1000000,
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'wallet_id' => $wallet->id,
            'transaction_date' => now()->addDays(5)->toDateString(),
            'amount' => 100000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateTransaction::class)
        ->fillForm(ledgerFormData($category, [
            'type' => TransactionType::Income->value,
            'wallet_id' => $wallet->id,
            'transaction_date' => now()->subDays(5)->toDateString(),
            'amount' => 50000,
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $ledgers = TransactionLedger::query()->orderBy('id')->get();

    expect($ledgers)->toHaveCount(2)
        ->and($ledgers[0]->transaction_type)->toBe(TransactionType::Income)
        ->and($ledgers[0]->opening_balance)->toBe('1000000.00')
        ->and($ledgers[0]->closing_balance)->toBe('1050000.00')
        ->and($ledgers[1]->transaction_type)->toBe(TransactionType::Expense)
        ->and($ledgers[1]->opening_balance)->toBe('1050000.00')
        ->and($ledgers[1]->closing_balance)->toBe('950000.00')
        ->and($wallet->refresh()->current_balance)->toBe('950000.00');
});
