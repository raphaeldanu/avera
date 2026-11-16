<?php

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Wallet::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignIdFor(Transaction::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('transaction_type');
            $table->decimal('opening_balance', 20, 2);
            $table->decimal('amount', 20, 2);
            $table->decimal('closing_balance', 20, 2);
            $table->timestamps();

            $table->index(['wallet_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_ledgers');
    }
};
