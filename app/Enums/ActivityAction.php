<?php

namespace App\Enums;

/**
 * Known activity-log action strings (DESIGN.md 3.10's "icon + action +
 * timestamp" timeline). Kept as an enum, not free-form strings, so every
 * call site agrees on exact spelling and the frontend has a closed set
 * to switch on for icons/copy.
 */
enum ActivityAction: string
{
    case MeetingCreated = 'meeting.created';
    case MeetingStatusChanged = 'meeting.status_changed';
    case TaskCreated = 'task.created';
    case TaskAssigned = 'task.assigned';
    case TaskStatusChanged = 'task.status_changed';
    case MemberInvited = 'member.invited';
    case MemberRemoved = 'member.removed';
    case MemberRoleChanged = 'member.role_changed';
    case CommentMention = 'comment.mention';
    case FileShared = 'file.shared';
}
