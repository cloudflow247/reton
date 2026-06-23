<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();

            $table->string('action_context', 32); // transfer | withdrawal | recovery ...
            $table->unsignedTinyInteger('score');
            $table->string('level', 8);            // low | medium | high
            $table->string('recommended_action', 16); // allow | challenge | hold | escalate | freeze
            $table->json('signals');               // list of triggered indicator codes

            $table->bigInteger('amount')->nullable();
            $table->char('currency', 3)->nullable();

            // The thing assessed (a transfer, recovery, etc.), if any.
            $table->nullableUuidMorphs('subject');

            $table->string('status', 16)->default('open'); // open | reviewed | dismissed | actioned
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
    }
};
