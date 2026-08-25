<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * FR-9.1 meeting reminders — the counterpart to Phase 5's
 * tasks:send-reminders. Notifies the owner and every accepted
 * participant of a scheduled meeting starting within the next hour.
 */
class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Notify owners and accepted participants of meetings starting within the next hour';

    public function handle(MeetingRepositoryInterface $meetings, NotificationService $notifications): int
    {
        $due = $meetings->dueForReminder();
        $notifiedCount = 0;

        foreach ($due as $meeting) {
            $recipients = collect([$meeting->owner])
                ->merge(
                    $meeting->participants()
                        ->where('invite_status', 'accepted')
                        ->with('user')
                        ->get()
                        ->pluck('user'),
                )
                ->filter()
                ->unique('id');

            foreach ($recipients as $recipient) {
                $notifications->notify($recipient, NotificationType::MeetingReminder, [
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                    'date' => $meeting->date?->toDateString(),
                    'time' => $meeting->time,
                ]);
                $notifiedCount++;
            }

            $meeting->update(['last_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$notifiedCount} meeting reminder(s) across {$due->count()} meeting(s).");

        return self::SUCCESS;
    }
}
