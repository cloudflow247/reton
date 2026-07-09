<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Hash;

$ada = User::where('email', 'ada@demo.retonpay.com')->firstOrFail();
$bola = User::where('email', 'bola@demo.retonpay.com')->firstOrFail();

app(WalletService::class)->open($ada, 'NGN');
$bolaWallet = app(WalletService::class)->open($bola, 'NGN');

$marketplace = app(DigitalMarketplaceService::class);

$listing = $marketplace->createListing(
    $ada,
    'UI kit — Lagos Fintech',
    'Figma component library with mobile wallet patterns. Instant download after delivery.',
    Money::of(15_000_00, 'NGN'),
    "Download: https://demo.retonpay.com/files/lagos-ui-kit.zip\nLicense: RETON-DEMO-SCREENSHOT",
);

// Active protected order for Protection / Marketplace order cards.
$order = $marketplace->purchase($bola, $listing->fresh(), $bolaWallet->refresh());

// Fresh shareable listing (still active) for guest / buyer screenshots.
$shareListing = $marketplace->createListing(
    $bola,
    'eBook — Trust-First Payments',
    'PDF guide to callback protection and wrong-transfer recovery for African fintech.',
    Money::of(5_000_00, 'NGN'),
    "Download: https://demo.retonpay.com/files/trust-first-payments.pdf\nAccess code: BOLA-SCREENSHOT",
);

echo json_encode([
    'share_listing_id' => $shareListing->id,
    'active_order_id' => $order->id,
    'seller_email' => $ada->email,
    'buyer_email' => $bola->email,
], JSON_THROW_ON_ERROR);
