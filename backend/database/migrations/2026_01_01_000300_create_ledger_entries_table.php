<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUuid('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();

            $table->string('direction', 6); // debit | credit
            $table->bigInteger('amount'); // positive minor units
            $table->char('currency', 3);

            $table->json('metadata')->nullable();

            // Entries are immutable accounting facts: created, never updated.
            $table->timestamp('created_at')->nullable();

            $table->index(['ledger_account_id', 'direction']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
