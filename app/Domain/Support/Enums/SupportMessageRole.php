<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum SupportMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
