<?php

declare(strict_types=1);

use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Support\ListingItemCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_listings', function (Blueprint $table): void {
            $table->string('item_code', 16)->nullable()->unique()->after('id');
        });

        DigitalListing::query()->whereNull('item_code')->each(function (DigitalListing $listing): void {
            $listing->update(['item_code' => ListingItemCodes::generate()]);
        });
    }

    public function down(): void
    {
        Schema::table('digital_listings', function (Blueprint $table): void {
            $table->dropUnique(['item_code']);
            $table->dropColumn('item_code');
        });
    }
};
