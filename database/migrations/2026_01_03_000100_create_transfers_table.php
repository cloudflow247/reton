<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('sender_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('receiver_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 16);   // normal | protected
            $table->string('status', 16); // pending | completed | held | refunded | failed
            $table->char('currency', 3);
            $table->bigInteger('amount');
            $table->string('note')->nullable();

            // The opening ledger posting (internal transfer for normal, hold for protected).
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();

            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['sender_wallet_id', 'status']);
            $table->index(['receiver_wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
