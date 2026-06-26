<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recovery_id')->constrained('recoveries')->cascadeOnDelete();

            $table->nullableUuidMorphs('actor');
            $table->string('action', 24);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('recovery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_events');
    }
};
