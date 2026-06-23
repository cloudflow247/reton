<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('callback_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('callback_id')->constrained('callbacks')->cascadeOnDelete();

            // Who performed the action; null for system / automated actions.
            $table->nullableUuidMorphs('actor');
            $table->string('action', 24);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Events are an immutable audit trail: created, never updated.
            $table->timestamp('created_at')->nullable();

            $table->index('callback_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_events');
    }
};
