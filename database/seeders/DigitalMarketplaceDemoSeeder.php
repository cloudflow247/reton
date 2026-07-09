<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;

/**
 * Sample digital listings for demo accounts (Ada sells to Bola and vice versa).
 */
class DigitalMarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ada = User::where('email', 'ada@demo.reton.ng')->first();
        $bola = User::where('email', 'bola@demo.reton.ng')->first();

        if (! $ada || ! $bola) {
            return;
        }

        $marketplace = app(DigitalMarketplaceService::class);

        if (! DigitalListing::where('seller_id', $ada->getKey())->where('status', 'active')->exists()) {
            $marketplace->createListing(
                $ada,
                'UI kit — Lagos Fintech',
                'Figma component library with mobile wallet patterns. Instant download after delivery.',
                Money::of(15_000_00, 'NGN'),
                "Download: https://demo.reton.ng/files/lagos-ui-kit.zip\nLicense: RETON-DEMO-ADA-001",
            );
        }

        if (! DigitalListing::where('seller_id', $bola->getKey())->where('status', 'active')->exists()) {
            $marketplace->createListing(
                $bola,
                'eBook — Trust-First Payments',
                'PDF guide to callback protection and wrong-transfer recovery for African fintech.',
                Money::of(5_000_00, 'NGN'),
                "Download: https://demo.reton.ng/files/trust-first-payments.pdf\nAccess code: BOLA-TRUST-42",
            );
        }

        if (! DigitalListing::where('seller_id', $ada->getKey())->where('item_type', 'physical')->where('status', 'active')->exists()) {
            $marketplace->createPhysicalListing(
                $ada,
                'Wireless earbuds — Lagos edition',
                'Barely used premium wireless earbuds with active noise cancellation, USB-C case, and 28-hour battery life. Ships in original box with all accessories.',
                Money::of(45_000_00, 'NGN'),
                \App\Domain\Marketplace\Enums\ItemCondition::LikeNew,
                250,
                ['brand' => 'SoundPro', 'detail' => 'Matte black, one size'],
                'Fragile — handle with care. Giglogistics pickup from Lekki.',
            );
        }
    }
}
