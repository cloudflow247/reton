<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('provider', 32)->default('alatpay');
            $table->string('provider_reference')->nullable()->index();

            $table->string('status', 16); // pending | completed | failed

            $table->bigInteger('amount');
            $table->char('currency', 3);

            $table->string('bank_code', 16);
            $table->string('account_number', 16);
            $table->string('account_name');

            // Two-phase ledger: reservation on request, settlement (or reversal) on result.
            $table->foreignUuid('reservation_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUuid('settlement_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
