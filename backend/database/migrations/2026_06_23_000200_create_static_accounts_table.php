<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')->unique()->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('provider', 32)->default('alatpay');
            $table->string('provider_reference')->nullable()->index(); // AlatPay staticWalletId

            $table->string('wallet_type', 16);  // individual | collection
            $table->string('status', 16);        // pending_otp | active | failed

            $table->string('account_number')->nullable(); // AlatPay external payable account
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();

            $table->string('otp_tracking_id')->nullable();
            $table->string('email')->nullable();

            $table->timestamp('last_polled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_accounts');
    }
};
