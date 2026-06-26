<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Enums;

enum FraudRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
