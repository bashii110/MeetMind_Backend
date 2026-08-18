<?php

namespace App\Enums;

/** DESIGN.md 3.6: "Meeting Mood (shown as a small sentiment badge, e.g.
 *  positive / neutral / tense)." */
enum MeetingMood: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Tense = 'tense';
}
