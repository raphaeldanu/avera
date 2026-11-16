<?php

use App\Enums\WalletType;
use App\Filament\Resources\Wallets\Pages\CreateWallet;
use App\Models\User;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('rejects a negative initial balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(CreateWallet::class)
        ->fillForm([
            'name' => 'Cash Wallet',
            'type' => WalletType::CASH->value,
            'initial_balance' => -1,
        ])
        ->call('create')
        ->assertHasFormErrors(['initial_balance' => 'min']);

    expect(Wallet::query()->count())->toBe(0);
});
