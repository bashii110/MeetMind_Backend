<?php

namespace App\Enums;

/**
 * FR-9.1. Only meeting-invitation is wired up in Phase 2; the rest are
 * declared now so NotificationResource/frontend switch-statements have a
 * stable, complete set of cases to branch on as later phases add them.
 */
enum NotificationType: string
{
    case MeetingInvitation = 'meeting_invitation';
    case MeetingReminder = 'meeting_reminder';
    case TaskAssigned = 'task_assigned';
    case TaskCompleted = 'task_completed';
    case Deadline = 'deadline';
    case Mention = 'mention';
    case WorkspaceInvitation = 'workspace_invitation';
}
