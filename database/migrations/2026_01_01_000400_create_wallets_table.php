<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->nullableUuidMorphs('owner');
            $table->char('currency', 3);

            // Cached projection of the wallet's ledger balance (minor units).
            // available = balance - held_balance.
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('held_balance')->default(0);

            $table->string('status', 16)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
