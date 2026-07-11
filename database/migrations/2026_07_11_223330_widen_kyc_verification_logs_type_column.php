<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_verification_logs', function (Blueprint $table): void {
            $table->string('type', 32)->change();
            $table->string('status', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kyc_verification_logs', function (Blueprint $table): void {
            $table->string('type', 8)->change();
            $table->string('status', 16)->change();
        });
    }
};
