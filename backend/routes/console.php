<?php

use App\Console\Commands\AutoReleaseTransfers;
use App\Console\Commands\EscalateRecoveries;
use App\Console\Commands\ExpireCallbacks;
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
