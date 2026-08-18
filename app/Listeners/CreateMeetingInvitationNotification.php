<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\ParticipantInvited;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateMeetingInvitationNotification implements ShouldQueue
{
    public $queue = 'notifications'; // ARCHITECTURE.md 3.5 — dedicated notifications queue

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(ParticipantInvited $event): void
    {
        $this->notifications->notify($event->invitee, NotificationType::MeetingInvitation, [
            'meeting_id' => $event->meeting->id,
            'meeting_title' => $event->meeting->title,
            'invited_by_id' => $event->invitedBy->id,
            'invited_by_name' => $event->invitedBy->name,
        ]);
    }
}
