<?php

namespace App\Enums;

/** FR-16.3: admin content-moderation queue. */
enum ContentReportStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';
}
