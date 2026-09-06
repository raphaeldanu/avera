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
        Schema::create('transaction_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Transaction::class);
            $table->foreignIdFor(Wallet::class);
            $table->decimal('amount', 20, 2);
            $table->timestamps();

            $table->index(['wallet_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_entries');
    }
};
