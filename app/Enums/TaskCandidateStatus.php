<?php

namespace App\Enums;

/** FR-6.3. */
enum TaskCandidateStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';
}
