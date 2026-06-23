<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // A short, shareable 10-digit account number (NUBAN-style).
            $table->char('account_number', 10)->nullable()->unique()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['account_number']);
            $table->dropColumn('account_number');
        });
    }
};
