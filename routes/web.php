<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AdminAppSettingsController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminIntegrationsController;
use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\AddMoneyController;
use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\RegisteredUserController;
use App\Http\Controllers\Web\BillsController;
use App\Http\Controllers\Web\CardsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MarketplaceController;
use App\Http\Controllers\Web\KycController;
use App\Http\Controllers\Web\PinController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReceiveController;
use App\Http\Controllers\Web\ProtectionController;
use App\Http\Controllers\Web\SendController;
use App\Http\Controllers\Web\SupportController;
use App\Http\Controllers\Web\WalletLookupController;
use App\Http\Controllers\Web\WellKnownController;
use App\Http\Controllers\Web\WithdrawController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public marketing pages
|--------------------------------------------------------------------------
| Authenticated visitors are bounced to their dashboard so "/" stays the
| marketing home for signed-out users only.
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Public/Home');
})->name('home');

Route::inertia('/security', 'Public/Security')->name('security');
Route::inertia('/how-it-works', 'Public/HowItWorks')->name('how-it-works');
Route::inertia('/business', 'Public/Business')->name('business');
Route::inertia('/about', 'Public/About')->name('about');
Route::inertia('/faq', 'Public/Faq')->name('faq');
Route::inertia('/contact', 'Public/Contact')->name('contact');

/*
|--------------------------------------------------------------------------
| Shareable links & mobile deep-link association
|--------------------------------------------------------------------------
| /l/{uuid} is the canonical listing URL for WhatsApp shares and future
| Universal Links / App Links. Keep this path stable across web and mobile.
*/
Route::get('/.well-known/apple-app-site-association', [WellKnownController::class, 'appleAppSiteAssociation'])
    ->name('well-known.aasa');
Route::get('/.well-known/assetlinks.json', [WellKnownController::class, 'assetLinks'])
    ->name('well-known.assetlinks');

Route::get('/robots.txt', function () {
    $robots = (string) config('reton.seo.robots', 'index,follow');
    $base = rtrim((string) (config('reton.links.public_base') ?: config('app.url')), '/');

    $lines = ['User-agent: *'];

    if (str_contains(strtolower($robots), 'noindex')) {
        $lines[] = 'Disallow: /';
    } else {
        $lines[] = 'Allow: /';
    }

    $lines[] = 'Sitemap: '.$base.'/sitemap.xml';

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::get('/sitemap.xml', function () {
    $base = rtrim((string) (config('reton.links.public_base') ?: config('app.url')), '/');
    $paths = ['/', '/security', '/how-it-works', '/business', '/about', '/faq', '/contact'];
    $lastmod = now()->toDateString();

    $urls = collect($paths)->map(fn (string $path) => "  <url>\n    <loc>{$base}{$path}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n  </url>")->implode("\n");

    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>";

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('/l/{listing}', [MarketplaceController::class, 'show'])->name('marketplace.listings.show');

/*
|--------------------------------------------------------------------------
| Guest auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:auth');

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:auth');
});

/*
|--------------------------------------------------------------------------
| Authenticated app (Inertia)
|--------------------------------------------------------------------------
| GET screens that only need the shared `auth` prop render directly; data and
| mutations route through Web controllers that reuse the domain services and
| the existing API FormRequests.
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Send + name enquiry + transfer
    Route::inertia('/send', 'Send')->name('send');
    Route::get('/lookup', [WalletLookupController::class, 'show'])->name('wallets.lookup');
    Route::post('/transfers', [SendController::class, 'store'])->name('transfers.store');

    // Add money (deposit) + receive
    Route::get('/add-money', [AddMoneyController::class, 'index'])->name('add-money');
    Route::get('/add-money/return/{reference}', [AddMoneyController::class, 'returnFromAlatpay'])->name('add-money.return');
    Route::get('/deposits/{deposit}/pay', [AddMoneyController::class, 'pay'])->name('deposits.pay');
    Route::post('/deposits/{deposit}/simulate-pay', [AddMoneyController::class, 'simulatePay'])->name('deposits.simulate-pay');
    Route::post('/deposits', [AddMoneyController::class, 'store'])->name('deposits.store');
    Route::get('/receive', [ReceiveController::class, 'index'])->name('receive');
    Route::post('/static-account', [ReceiveController::class, 'provision'])->name('static-account.provision');
    Route::post('/static-account/{staticAccount}/verify', [ReceiveController::class, 'verify'])->name('static-account.verify');

    // Bill payments (airtime, utilities, Remita RRR)
    Route::get('/bills', [BillsController::class, 'index'])->name('bills');
    Route::get('/bills/rrr', [BillsController::class, 'lookup'])->name('bills.rrr');
    Route::post('/bills', [BillsController::class, 'store'])->name('bills.store');

    // Withdraw to personal bank (same-name policy)
    Route::get('/withdraw', [WithdrawController::class, 'index'])->name('withdraw');
    Route::post('/withdraw', [WithdrawController::class, 'store'])->name('withdraw.store');

    // Activity + profile
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/kyc/tier-2', [KycController::class, 'upgradeTier2'])->middleware('throttle:6,1')->name('profile.kyc.tier2');
    Route::post('/profile/kyc/tier-3', [KycController::class, 'upgradeTier3'])->middleware('throttle:6,1')->name('profile.kyc.tier3');

    // Virtual cards (Bridgecard NGN & USD)
    Route::get('/cards', [CardsController::class, 'index'])->name('cards');
    Route::post('/cards', [CardsController::class, 'store'])->name('cards.store');
    Route::post('/cards/{card}/fund', [CardsController::class, 'fund'])->name('cards.fund');
    Route::get('/cards/fund/quote', [CardsController::class, 'quote'])->name('cards.fund.quote');
    Route::post('/cards/reveal', [CardsController::class, 'reveal'])->name('cards.reveal');
    Route::post('/cards/freeze', [CardsController::class, 'freeze'])->name('cards.freeze');
    Route::post('/cards/unfreeze', [CardsController::class, 'unfreeze'])->name('cards.unfreeze');

    // Transaction PIN
    Route::inertia('/pin', 'SetPin')->name('pin');
    Route::post('/pin', [PinController::class, 'update'])->name('pin.update');

    // Digital marketplace — protected purchases between users
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
    Route::post('/marketplace/listings', [MarketplaceController::class, 'store'])->name('marketplace.listings.store');
    Route::post('/marketplace/listings/{listing}/purchase', [MarketplaceController::class, 'purchase'])->name('marketplace.listings.purchase');
    Route::post('/marketplace/orders/{order}/ship', [MarketplaceController::class, 'ship'])->name('marketplace.orders.ship');
    Route::post('/marketplace/orders/{order}/deliver', [MarketplaceController::class, 'deliver'])->name('marketplace.orders.deliver');
    Route::post('/marketplace/orders/{order}/confirm', [MarketplaceController::class, 'confirm'])->name('marketplace.orders.confirm');
    Route::post('/marketplace/orders/{order}/dispute', [MarketplaceController::class, 'dispute'])->name('marketplace.orders.dispute');

    // Protection center — held transfers, callbacks, recoveries
    Route::get('/protection', [ProtectionController::class, 'index'])->name('protection');
    Route::post('/transfers/{transfer}/release', [ProtectionController::class, 'release'])->name('transfers.release');
    Route::post('/transfers/{transfer}/callbacks', [ProtectionController::class, 'storeCallback'])->name('callbacks.store');
    Route::post('/callbacks/{callback}/accept', [ProtectionController::class, 'acceptCallback'])->name('callbacks.accept');
    Route::post('/callbacks/{callback}/reject', [ProtectionController::class, 'rejectCallback'])->name('callbacks.reject');
    Route::post('/transfers/{transfer}/recoveries', [ProtectionController::class, 'storeRecovery'])->name('recoveries.store');
    Route::post('/recoveries/{recovery}/return', [ProtectionController::class, 'returnRecovery'])->name('recoveries.return');
    Route::post('/recoveries/{recovery}/dispute', [ProtectionController::class, 'disputeRecovery'])->name('recoveries.dispute');

    // AI support — rule-based assistant with transaction lookup and escalation
    Route::get('/support', [SupportController::class, 'index'])->name('support');
    Route::post('/support/messages', [SupportController::class, 'storeMessage'])->name('support.messages.store');
    Route::post('/support/escalate', [SupportController::class, 'escalate'])->name('support.escalate');

    /*
    |--------------------------------------------------------------------------
    | Platform admin — path segment is configurable in App settings
    |--------------------------------------------------------------------------
    */
    Route::middleware(['admin', 'admin.path'])
        ->prefix('{adminPrefix}')
        ->where(['adminPrefix' => '[a-z0-9\-]+'])
        ->name('admin.')
        ->group(base_path('routes/admin.php'));

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
