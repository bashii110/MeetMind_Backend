<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\ParticipantInviteStatus;
use App\Events\ParticipantInvited;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    public function __construct(
        private readonly MeetingRepositoryInterface $meetings,
        private readonly TagRepositoryInterface $tags,
    ) {}

    public function listForUser(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->meetings->forUser($user, $filters, $perPage);
    }

    /**
     * @param array{tags?: array<string>, participant_emails?: array<string>} $data
     */
    public function create(User $owner, Workspace $workspace, array $data): Meeting
    {
        $meeting = $this->meetings->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'date' => $data['date'],
            'time' => $data['time'] ?? null,
            'location' => $data['location'] ?? null,
            'online_link' => $data['online_link'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? MeetingStatus::Draft->value,
        ]);

        if (! empty($data['tags'])) {
            $this->syncTags($meeting, $workspace, $data['tags']);
        }

        if (! empty($data['participant_emails'])) {
            $this->invite($meeting, $owner, $data['participant_emails']);
        }

        return $meeting->fresh(['owner', 'tags', 'participants.user']);
    }

    public function update(Meeting $meeting, array $data): Meeting
    {
        $attributes = collect($data)
            ->only([
                'title', 'description', 'date', 'time', 'location',
                'online_link', 'priority', 'category',
            ])
            ->toArray();

        $meeting = $this->meetings->update($meeting, $attributes);

        if (array_key_exists('tags', $data)) {
            $this->syncTags($meeting, $meeting->workspace, $data['tags'] ?? []);
        }

        return $meeting->fresh(['owner', 'tags', 'participants.user']);
    }

    public function delete(Meeting $meeting): void
    {
        $this->meetings->delete($meeting);
    }

    public function changeStatus(Meeting $meeting, MeetingStatus $next): Meeting
    {
        if (! $meeting->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$meeting->status->value} to {$next->value}."],
            ]);
        }

        return $this->meetings->update($meeting, ['status' => $next->value]);
    }

    /**
     * FR-3.4. Invites by email — the invitee must already have a MeetMind
     * account for now (no invite-a-non-user-by-email flow yet).
     *
     * @param array<string> $emails
     * @return array{invited: array<int>, not_found: array<string>}
     */
    public function invite(Meeting $meeting, User $invitedBy, array $emails): array
    {
        $users = User::query()->whereIn('email', $emails)->get()->keyBy('email');
        $notFound = array_values(array_diff($emails, $users->keys()->all()));

        $invitedIds = [];
        foreach ($users as $user) {
            if ($user->id === $meeting->owner_id) {
                continue; // owner doesn't need a participant row
            }

            $participant = $meeting->participants()->firstOrCreate(
                ['user_id' => $user->id],
                ['invite_status' => ParticipantInviteStatus::Pending->value],
            );

            if ($participant->wasRecentlyCreated) {
                event(new ParticipantInvited($meeting, $user, $invitedBy));
            }

            $invitedIds[] = $user->id;
        }

        return ['invited' => $invitedIds, 'not_found' => $notFound];
    }

    public function removeParticipant(Meeting $meeting, User $user): void
    {
        $meeting->participants()->where('user_id', $user->id)->delete();
    }

    public function respondToInvitation(Meeting $meeting, User $user, ParticipantInviteStatus $status): void
    {
        $participant = $meeting->participants()->where('user_id', $user->id)->firstOrFail();
        $participant->update(['invite_status' => $status->value]);
    }

    private function syncTags(Meeting $meeting, Workspace $workspace, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->filter()
            ->map(fn (string $name) => $this->tags->findOrCreate($workspace, $name)->id);

        $meeting->tags()->sync($tagIds);
    }
}
