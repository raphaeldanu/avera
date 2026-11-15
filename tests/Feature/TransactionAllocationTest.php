<?php

use App\Enums\TransactionType;
use App\Enums\WalletType;
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

    $this->user = User::factory()->create();

    $this->actingAs($this->user);

    $this->category = TransactionCategory::create([
        'user_id' => $this->user->id,
        'name' => 'Groceries',
    ]);

    $this->cash = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Cash',
        'type' => WalletType::CASH,
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $this->bank = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Bank',
        'type' => WalletType::BANK_ACCOUNT,
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $this->transactionData = [
        'title' => 'Weekly groceries',
        'transaction_date' => now()->toDateString(),
        'transaction_category_id' => $this->category->id,
        'type' => TransactionType::Expense->value,
        'amount' => 100000,
    ];
});

it('creates a transaction with wallet allocations', function () {
    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...$this->transactionData,
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 60000],
                ['wallet_id' => $this->bank->id, 'amount' => 40000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $transaction = Transaction::query()->sole();

    expect($transaction->amount)->toBe('100000.00')
        ->and($transaction->user_id)->toBe($this->user->id)
        ->and($transaction->transactionEntries)->toHaveCount(2)
        ->and((float) $transaction->transactionEntries->sum('amount'))->toBe(100000.0);
});

it('requires at least one wallet allocation', function () {
    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...$this->transactionData,
            'transactionEntries' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['transactionEntries']);

    expect(Transaction::query()->count())->toBe(0);
});

it('requires wallet allocation amounts to be positive', function () {
    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...$this->transactionData,
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 0],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['transactionEntries.0.amount']);

    expect(Transaction::query()->count())->toBe(0);
});

it('does not allow the same wallet to be allocated twice', function () {
    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...$this->transactionData,
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 60000],
                ['wallet_id' => $this->cash->id, 'amount' => 40000],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['transactionEntries.0.wallet_id']);

    expect(Transaction::query()->count())->toBe(0);
});

it('requires wallet allocations to add up to the transaction amount', function () {
    Livewire::test(CreateTransaction::class)
        ->fillForm([
            ...$this->transactionData,
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 60000],
                ['wallet_id' => $this->bank->id, 'amount' => 30000],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['amount', 'transactionEntries']);

    expect(Transaction::query()->count())->toBe(0);
});

it('updates the wallet allocation composition when editing a transaction', function () {
    $transaction = Transaction::create([
        ...$this->transactionData,
        'user_id' => $this->user->id,
    ]);

    $transaction->transactionEntries()->createMany([
        ['wallet_id' => $this->cash->id, 'amount' => 60000],
        ['wallet_id' => $this->bank->id, 'amount' => 40000],
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->fillForm([
            'amount' => 120000,
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 20000],
                ['wallet_id' => $this->bank->id, 'amount' => 100000],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $transaction->refresh();

    expect($transaction->amount)->toBe('120000.00')
        ->and($transaction->transactionEntries)->toHaveCount(2)
        ->and((float) $transaction->transactionEntries->sum('amount'))->toBe(120000.0);
});

it('preserves existing wallet allocations when loading the edit form', function () {
    $transaction = Transaction::create([
        ...$this->transactionData,
        'user_id' => $this->user->id,
    ]);

    $transaction->transactionEntries()->createMany([
        ['wallet_id' => $this->cash->id, 'amount' => 60000],
        ['wallet_id' => $this->bank->id, 'amount' => 40000],
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->assertFormSet([
            'amount' => 100000,
        ])
        ->assertSee($this->cash->name)
        ->assertSee($this->bank->name);
});

it('allows wallet allocations to be removed when editing a transaction', function () {
    $transaction = Transaction::create([
        ...$this->transactionData,
        'user_id' => $this->user->id,
    ]);

    $transaction->transactionEntries()->createMany([
        ['wallet_id' => $this->cash->id, 'amount' => 60000],
        ['wallet_id' => $this->bank->id, 'amount' => 40000],
    ]);

    Livewire::test(EditTransaction::class, ['record' => $transaction->getRouteKey()])
        ->fillForm([
            'transactionEntries' => [
                ['wallet_id' => $this->cash->id, 'amount' => 100000],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $transaction->refresh();

    expect($transaction->transactionEntries)->toHaveCount(1)
        ->and($transaction->transactionEntries->first()->wallet_id)->toBe($this->cash->id);
});

it('shows the wallet allocation summary in the transactions table', function () {
    $transaction = Transaction::create([
        ...$this->transactionData,
        'user_id' => $this->user->id,
    ]);

    $transaction->transactionEntries()->createMany([
        ['wallet_id' => $this->cash->id, 'amount' => 60000],
        ['wallet_id' => $this->bank->id, 'amount' => 40000],
    ]);

    Livewire::test(ListTransactions::class)
        ->assertCanSeeTableRecords([$transaction])
        ->assertSee($this->cash->name)
        ->assertSee($this->bank->name);
});
