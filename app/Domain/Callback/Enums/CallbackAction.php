<?php

declare(strict_types=1);

namespace App\Domain\Callback\Enums;

enum CallbackAction: string
{
    case Initiated = 'initiated';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case EvidenceAdded = 'evidence_added';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Expired = 'expired';
}
