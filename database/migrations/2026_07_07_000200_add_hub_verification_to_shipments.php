<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_shipments', function (Blueprint $table) {
            $table->string('dropoff_code', 16)->nullable()->after('tracking_number');
            $table->string('hub_name', 120)->nullable()->after('dropoff_code');
            $table->json('hub_address')->nullable()->after('hub_name');
            $table->string('hub_verification_status', 24)->nullable()->after('hub_address');
            $table->unsignedTinyInteger('hub_verification_score')->nullable()->after('hub_verification_status');
            $table->json('hub_verification_report')->nullable()->after('hub_verification_score');
            $table->timestamp('received_at_hub_at')->nullable()->after('hub_verification_report');
            $table->timestamp('verified_at')->nullable()->after('received_at_hub_at');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'dropoff_code',
                'hub_name',
                'hub_address',
                'hub_verification_status',
                'hub_verification_score',
                'hub_verification_report',
                'received_at_hub_at',
                'verified_at',
            ]);
        });
    }
};
