<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Enums;

enum FraudAction: string
{
    case Allow = 'allow';
    case Challenge = 'challenge';
    case Hold = 'hold';
    case Escalate = 'escalate';
    case Freeze = 'freeze';

    /**
     * Whether this action stops the transaction from proceeding.
     */
    public function blocks(): bool
    {
        return match ($this) {
            self::Hold, self::Escalate, self::Freeze => true,
            self::Allow, self::Challenge => false,
        };
    }
}
