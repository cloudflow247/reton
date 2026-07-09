<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

$providers = [
    AppServiceProvider::class,
];

// Horizon requires ext-pcntl / ext-posix (not available on Windows PHP).
if (PHP_OS_FAMILY !== 'Windows') {
    $providers[] = HorizonServiceProvider::class;
}

return $providers;
