<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('provider', 32)->default('remita');
            $table->string('provider_reference')->nullable()->index();

            $table->string('status', 16); // pending | completed | failed

            $table->string('category', 24); // airtime | data | electricity | cable_tv | rrr
            $table->string('biller_code', 64);
            $table->string('biller_name');
            $table->string('customer_reference', 64); // phone / meter / RRR

            $table->bigInteger('amount');
            $table->char('currency', 3);

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
        Schema::dropIfExists('bill_payments');
    }
};
