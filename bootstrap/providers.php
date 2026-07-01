<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

// Horizon requires ext-pcntl / ext-posix (not available on Windows PHP).
if (PHP_OS_FAMILY !== 'Windows') {
    $providers[] = App\Providers\HorizonServiceProvider::class;
}

return $providers;
