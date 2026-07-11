<?php

declare(strict_types=1);

use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\AddMoneyController;
use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Web\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Web\Auth\NewPasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetLinkController;
use App\Http\Controllers\Web\Auth\RegisteredUserController;
use App\Http\Controllers\Web\Auth\VerifyEmailController;
use App\Http\Controllers\Web\BillsController;
use App\Http\Controllers\Web\CardsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\KycController;
use App\Http\Controllers\Web\MarketplaceController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\PinController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ProtectionController;
use App\Http\Controllers\Web\ReceiveController;
use App\Http\Controllers\Web\SendController;
use App\Http\Controllers\Web\SupportController;
use App\Http\Controllers\Web\WalletLookupController;
use App\Http\Controllers\Web\WellKnownController;
use App\Http\Controllers\Web\WithdrawController;
use App\Models\User;
use App\Support\Admin\AdminPath;
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
    if (! auth()->check()) {
        return Inertia::render('Public/Home');
    }

    /** @var User $user */
    $user = auth()->user();

    if (! $user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    if ($user->is_admin) {
        return redirect(AdminPath::url());
    }

    if (! $user->hasTransactionPin()) {
        return redirect()->route('onboarding');
    }

    return redirect()->route('dashboard');
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

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:auth')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:auth')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated — email verification (pre-wallet access)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Verified users — onboarding + wallet setup
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');

    Route::get('/add-money', [AddMoneyController::class, 'index'])->name('add-money');
    Route::post('/add-money/check-deposits', [AddMoneyController::class, 'checkDeposits'])
        ->middleware('throttle:12,1')
        ->name('add-money.check-deposits');
    Route::get('/add-money/return/{reference}', [AddMoneyController::class, 'returnFromAlatpay'])->name('add-money.return');
    Route::get('/deposits/{deposit}/pay', [AddMoneyController::class, 'pay'])->name('deposits.pay');
    Route::post('/deposits/{deposit}/simulate-pay', [AddMoneyController::class, 'simulatePay'])->name('deposits.simulate-pay');
    Route::post('/deposits', [AddMoneyController::class, 'store'])->name('deposits.store');

    Route::inertia('/pin', 'SetPin')->name('pin');
    Route::post('/pin', [PinController::class, 'update'])->name('pin.update');

    Route::post('/profile/kyc/tier-2', [KycController::class, 'upgradeTier2'])->middleware('throttle:6,1')->name('profile.kyc.tier2');
    Route::post('/profile/kyc/tier-2/confirm', [KycController::class, 'confirmTier2'])->middleware('throttle:12,1')->name('profile.kyc.tier2.confirm');
    Route::post('/profile/kyc/tier-2/resend', [KycController::class, 'resendTier2Otp'])->middleware('throttle:3,1')->name('profile.kyc.tier2.resend');
});

/*
|--------------------------------------------------------------------------
| Authenticated app (Inertia) — verified + onboarding complete
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Send + name enquiry + transfer
    Route::inertia('/send', 'Send')->name('send');
    Route::get('/lookup', [WalletLookupController::class, 'show'])->name('wallets.lookup');
    Route::post('/transfers', [SendController::class, 'store'])->name('transfers.store');

    // Receive (deposits use add-money route in verified group)
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
    Route::get('/activity/{entry}', [ActivityController::class, 'show'])->name('activity.show');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::post('/profile/kyc/tier-3', [KycController::class, 'upgradeTier3'])->middleware('throttle:6,1')->name('profile.kyc.tier3');

    // Virtual cards (Bridgecard NGN & USD)
    Route::get('/cards', [CardsController::class, 'index'])->name('cards');
    Route::post('/cards', [CardsController::class, 'store'])->name('cards.store');
    Route::post('/cards/{card}/fund', [CardsController::class, 'fund'])->name('cards.fund');
    Route::get('/cards/fund/quote', [CardsController::class, 'quote'])->name('cards.fund.quote');
    Route::post('/cards/reveal', [CardsController::class, 'reveal'])->name('cards.reveal');
    Route::post('/cards/freeze', [CardsController::class, 'freeze'])->name('cards.freeze');
    Route::post('/cards/unfreeze', [CardsController::class, 'unfreeze'])->name('cards.unfreeze');

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
});

/*
|--------------------------------------------------------------------------
| Platform admin — registered last so /dashboard and other literals win
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin.path', 'admin'])
    ->prefix('{adminPrefix}')
    ->where(['adminPrefix' => '[a-z0-9\-]+'])
    ->name('admin.')
    ->group(base_path('routes/admin.php'));
