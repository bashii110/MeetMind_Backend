<?php

namespace App\Support;

use App\Enums\NotificationType;

/**
 * Turns a notification type + payload into human-readable push copy
 * (FR-9.1). The frontend already renders its own per-type UI once the
 * app is open (see notification_list_screen.dart's _title()); push needs
 * a title/body up front since the OS renders the banner before the app
 * (or the person) is involved at all.
 */
class NotificationCopy
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string} [title, body]
     */
    public static function for(NotificationType $type, array $payload): array
    {
        return match ($type) {
            NotificationType::MeetingInvitation => [
                'Meeting invitation',
                sprintf(
                    '%s invited you to "%s"',
                    $payload['invited_by_name'] ?? 'Someone',
                    $payload['meeting_title'] ?? 'a meeting',
                ),
            ],
            NotificationType::MeetingReminder => [
                'Upcoming meeting',
                sprintf('"%s" is starting soon', $payload['meeting_title'] ?? 'Your meeting'),
            ],
            NotificationType::TaskAssigned => [
                'New task assigned',
                sprintf(
                    '%s assigned you "%s"',
                    $payload['assigned_by_name'] ?? 'Someone',
                    $payload['task_title'] ?? 'a task',
                ),
            ],
            NotificationType::TaskCompleted => [
                'Task completed',
                sprintf('"%s" was marked complete', $payload['task_title'] ?? 'A task'),
            ],
            NotificationType::Deadline => [
                'Deadline approaching',
                sprintf('"%s" is due soon', $payload['task_title'] ?? 'A task'),
            ],
            NotificationType::Mention => [
                'You were mentioned',
                sprintf('%s mentioned you in a comment', $payload['mentioned_by_name'] ?? 'Someone'),
            ],
            NotificationType::WorkspaceInvitation => [
                'Workspace invitation',
                sprintf('You were invited to join "%s"', $payload['workspace_name'] ?? 'a workspace'),
            ],
        };
    }
}
