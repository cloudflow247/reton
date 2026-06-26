<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('provider', 32)->default('alatpay');
            $table->string('provider_reference')->nullable()->index();

            $table->string('status', 16); // pending | paid | expired | cancelled
            $table->bigInteger('amount');
            $table->char('currency', 3);

            $table->string('title');
            $table->string('description')->nullable();
            $table->string('payment_link_url')->nullable();

            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();

            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['requester_user_id', 'status']);
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
