<?php

use App\Domain\Settings\Services\PlatformSettingsService;
use Illuminate\Support\Facades\DB;

it('does not throw when the database file is missing during boot', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => '/tmp/reton-missing-build.sqlite',
    ]);

    DB::purge('sqlite');

    expect(fn () => app(PlatformSettingsService::class)->applyToConfig())
        ->not->toThrow(Throwable::class);
});
