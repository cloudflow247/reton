<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();

            $table->foreignUuid('transfer_id')->constrained('transfers')->cascadeOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 16); // pending | escalated | released | refunded
            $table->string('reason')->nullable();
            $table->string('resolution', 16)->nullable(); // release | refund
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('responds_by')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['transfer_id', 'status']);
            $table->index(['status', 'responds_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callbacks');
    }
};
