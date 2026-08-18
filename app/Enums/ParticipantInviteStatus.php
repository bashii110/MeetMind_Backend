<?php

namespace App\Enums;

/** SRD FR-3.4. */
enum ParticipantInviteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
