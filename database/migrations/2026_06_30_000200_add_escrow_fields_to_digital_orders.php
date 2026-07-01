<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_orders', function (Blueprint $table) {
            $table->timestamp('delivery_deadline_at')->nullable()->after('completed_at');
            $table->timestamp('seller_attested_at')->nullable()->after('delivery_deadline_at');
            $table->string('payload_checksum', 64)->nullable()->after('seller_attested_at');
            $table->timestamp('buyer_reviewed_at')->nullable()->after('payload_checksum');
            $table->boolean('buyer_satisfied')->nullable()->after('buyer_reviewed_at');
            $table->string('dispute_category', 32)->nullable()->after('buyer_satisfied');
        });
    }

    public function down(): void
    {
        Schema::table('digital_orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_deadline_at',
                'seller_attested_at',
                'payload_checksum',
                'buyer_reviewed_at',
                'buyer_satisfied',
                'dispute_category',
            ]);
        });
    }
};
