<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recoveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignUuid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('sender_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('receiver_wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('status', 16); // held | escalated | returned | declined
            $table->string('reason')->nullable();
            $table->string('resolution', 16)->nullable(); // return | release
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->char('currency', 3);
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recoveries');
    }
};
