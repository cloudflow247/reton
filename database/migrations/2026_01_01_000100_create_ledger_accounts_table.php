<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 16);
            $table->char('currency', 3);

            // Optional owner (wallet, merchant, user). System accounts have none.
            $table->nullableUuidMorphs('owner');
            $table->boolean('is_system')->default(false);

            // Cached running totals. Authoritative balances remain derivable
            // from ledger_entries; these are an audited projection updated only
            // inside the posting transaction. Stored in integer minor units.
            $table->bigInteger('posted_debits')->default(0);
            $table->bigInteger('posted_credits')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
