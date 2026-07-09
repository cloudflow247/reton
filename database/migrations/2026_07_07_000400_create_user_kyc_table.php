<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_kyc', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->text('bvn_encrypted')->nullable();
            $table->string('bvn_hash', 64)->nullable()->unique();
            $table->string('bvn_last4', 4)->nullable();
            $table->text('nin_encrypted')->nullable();
            $table->string('nin_hash', 64)->nullable()->unique();
            $table->string('nin_last4', 4)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamp('bvn_verified_at')->nullable();
            $table->timestamp('nin_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_kyc');
    }
};
