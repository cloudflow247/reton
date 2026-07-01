<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->bigInteger('price');
            $table->char('currency', 3)->default('NGN');
            $table->text('delivery_payload');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['seller_id', 'status']);
        });

        Schema::create('digital_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->constrained('digital_listings')->cascadeOnDelete();
            $table->foreignUuid('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->string('status', 24)->default('paid_held');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->unique('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_orders');
        Schema::dropIfExists('digital_listings');
    }
};
