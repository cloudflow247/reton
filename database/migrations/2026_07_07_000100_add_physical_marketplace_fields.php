<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_listings', function (Blueprint $table) {
            $table->string('item_type', 16)->default('digital')->after('seller_id');
            $table->string('condition', 24)->nullable()->after('description');
            $table->unsignedInteger('weight_grams')->nullable()->after('condition');
            $table->json('dimensions_cm')->nullable()->after('weight_grams');
            $table->json('specs')->nullable()->after('dimensions_cm');
            $table->text('handling_notes')->nullable()->after('specs');
            $table->string('verification_status', 16)->nullable()->after('handling_notes');
            $table->unsignedTinyInteger('verification_score')->nullable()->after('verification_status');
        });

        Schema::table('digital_orders', function (Blueprint $table) {
            $table->json('listing_snapshot')->nullable()->after('status');
            $table->timestamp('buyer_accepted_description_at')->nullable()->after('listing_snapshot');
            $table->string('verification_status', 16)->nullable()->after('buyer_accepted_description_at');
            $table->unsignedTinyInteger('verification_score')->nullable()->after('verification_status');
            $table->json('shipping_address')->nullable()->after('verification_score');
            $table->unsignedBigInteger('shipping_fee')->default(0)->after('shipping_address');
            $table->timestamp('shipped_at')->nullable()->after('delivered_at');
            $table->timestamp('received_at')->nullable()->after('shipped_at');
        });

        Schema::create('marketplace_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('digital_orders')->cascadeOnDelete();
            $table->string('carrier', 32)->default('giglogistics');
            $table->string('external_id')->nullable();
            $table->string('tracking_number', 64);
            $table->string('status', 24)->default('pending_pickup');
            $table->json('origin_address');
            $table->json('destination_address');
            $table->json('events')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('pod_reference', 64)->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['status', 'updated_at']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shipments');

        Schema::table('digital_orders', function (Blueprint $table) {
            $table->dropColumn([
                'listing_snapshot',
                'buyer_accepted_description_at',
                'verification_status',
                'verification_score',
                'shipping_address',
                'shipping_fee',
                'shipped_at',
                'received_at',
            ]);
        });

        Schema::table('digital_listings', function (Blueprint $table) {
            $table->dropColumn([
                'item_type',
                'condition',
                'weight_grams',
                'dimensions_cm',
                'specs',
                'handling_notes',
                'verification_status',
                'verification_score',
            ]);
        });
    }
};
