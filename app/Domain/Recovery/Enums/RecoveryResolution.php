<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Enums;

enum RecoveryResolution: string
{
    case Return = 'return';
    case Release = 'release';
}
