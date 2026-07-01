<?php

declare(strict_types=1);

/**
 * Cross-platform dev stack launcher.
 *
 * Windows PHP lacks pcntl/posix, so Horizon cannot run — we use queue:work instead.
 * Redis is optional on Windows; the database queue driver works with SQLite.
 */

$isWindows = PHP_OS_FAMILY === 'Windows';

$queueCommand = $isWindows
    ? 'php artisan queue:work database --tries=3 --sleep=1'
    : 'php artisan horizon';

$queueName = $isWindows ? 'queue' : 'horizon';

$concurrently = implode(' ', [
    'npx concurrently',
    '-c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#f472b6"',
    '"php artisan serve"',
    '"'.$queueCommand.'"',
    '"php artisan reverb:start"',
    '"php artisan pail --timeout=0"',
    '"npm run dev"',
    '--names=server,'.$queueName.',reverb,logs,vite',
    '--kill-others',
]);

passthru($concurrently, $exitCode);

exit($exitCode);
