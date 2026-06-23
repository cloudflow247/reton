<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Enums;

enum RecoveryAction: string
{
    case Reported = 'reported';
    case HeldPlaced = 'held_placed';
    case Returned = 'returned';
    case Disputed = 'disputed';
    case Escalated = 'escalated';
    case Released = 'released';
    case Declined = 'declined';
    case Expired = 'expired';
    case EvidenceAdded = 'evidence_added';
}
