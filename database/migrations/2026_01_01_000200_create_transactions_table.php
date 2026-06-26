<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            // Idempotency key: a second posting with the same key is a no-op
            // that returns the originally posted transaction.
            $table->string('idempotency_key')->nullable()->unique();

            $table->string('type', 32);
            $table->string('status', 16)->default('pending');
            $table->char('currency', 3);

            // Principal amount of the transaction (sum of debit legs), minor units.
            $table->bigInteger('amount');

            $table->string('description');
            $table->nullableUuidMorphs('initiator');
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
