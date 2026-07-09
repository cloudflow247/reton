<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->string('provider_card_id', 64)->nullable()->after('card_identifier');
            $table->string('provider_cardholder_id', 64)->nullable()->after('provider_card_id');
        });

        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_identifier']);
            $table->unique(['user_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'currency']);
            $table->unique(['user_id', 'card_identifier']);
        });

        Schema::table('virtual_cards', function (Blueprint $table) {
            $table->dropColumn(['provider_card_id', 'provider_cardholder_id']);
        });
    }
};
