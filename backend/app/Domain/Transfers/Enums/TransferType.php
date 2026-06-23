<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Enums;

enum TransferType: string
{
    case Normal = 'normal';
    case Protected = 'protected';
}
