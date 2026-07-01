<?php

use App\Console\Commands\AutoReleaseTransfers;
use App\Console\Commands\EscalateRecoveries;
use App\Console\Commands\ExpireCallbacks;
use App\Console\Commands\ExpireUndeliveredDigitalOrders;
use App\Console\Commands\PollStaticAccounts;
use App\Console\Commands\ReconcileDeposits;
use App\Console\Commands\ReconcilePayouts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Callback protection schedule
|--------------------------------------------------------------------------
|
| Expire overdue callbacks first (so any dispute is resolved), then
| auto-release protected transfers whose hold window has elapsed and that no
| longer have an open callback. Both run without overlap to keep ledger
| postings serialised.
|
*/

Schedule::command(ExpireCallbacks::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(ExpireUndeliveredDigitalOrders::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(AutoReleaseTransfers::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(EscalateRecoveries::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(ReconcileDeposits::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command(ReconcilePayouts::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Static account funding
|--------------------------------------------------------------------------
|
| Poll active AlatPay static accounts for new inbound payments and credit the
| owning wallets. This is the primary funding path for static accounts (AlatPay
| exposes a transactions endpoint rather than a webhook), so it runs every
| minute for prompt credit. Crediting is idempotent on the AlatPay transaction
| id, and withoutOverlapping keeps ledger postings serialised.
|
*/

Schedule::command(PollStaticAccounts::class)
    ->everyMinute()
    ->withoutOverlapping();
