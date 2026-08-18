<?php

namespace App\Enums;

/**
 * Deliberately separate from MeetingPriority even though the values
 * currently overlap (low/medium/high) — they're different domain
 * concepts and shouldn't be coupled just because they happen to match
 * today (e.g. a future "urgent" tier for tasks shouldn't force one onto
 * meetings too).
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
