<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Summary;
use App\Models\Task;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FR-12.1: global search across meeting titles, transcripts, tasks,
 * users, dates, tags, and AI summaries. Implemented with plain `LIKE`
 * queries — like every other search in this codebase (see
 * MeetingRepository::forUser / TaskRepository::forUser) — rather than
 * MySQL FULLTEXT indexes, so the same code path works against both the
 * production MySQL database and the SQLite connection the test suite
 * runs against (see phpunit.xml).
 */
class SearchService
{
    private const PER_CATEGORY_LIMIT = 10;

    /**
     * @return array{
     *   meetings: Collection<int, Meeting>,
     *   tasks: Collection<int, Task>,
     *   transcript_meetings: Collection<int, Meeting>,
     *   summary_meetings: Collection<int, Meeting>,
     *   users: Collection<int, User>,
     * }
     */
    public function search(User $user, string $query): array
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');
        $term = "%{$query}%";

        $meetings = Meeting::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $term));
            })
            ->with(['owner', 'tags'])
            ->latest('date')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get();

        $tasks = Task::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->with(['assignee', 'creator'])
            ->latest()
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get();

        $meetingIdsInScope = Meeting::query()->whereIn('workspace_id', $workspaceIds)->pluck('id');

        $transcriptMeetingIds = Transcript::query()
            ->whereIn('meeting_id', $meetingIdsInScope)
            ->where('text', 'like', $term)
            ->limit(self::PER_CATEGORY_LIMIT)
            ->pluck('meeting_id');

        $transcriptMeetings = Meeting::query()
            ->whereIn('id', $transcriptMeetingIds)
            ->with(['owner', 'tags'])
            ->get();

        $summaryMeetingIds = Summary::query()
            ->whereIn('meeting_id', $meetingIdsInScope)
            ->where(function (Builder $q) use ($term) {
                $q->where('executive_summary', 'like', $term)
                    ->orWhere('decisions', 'like', $term)
                    ->orWhere('next_steps', 'like', $term);
            })
            ->limit(self::PER_CATEGORY_LIMIT)
            ->pluck('meeting_id');

        $summaryMeetings = Meeting::query()
            ->whereIn('id', $summaryMeetingIds)
            ->with(['owner', 'tags'])
            ->get();

        $users = User::query()
            ->whereHas('workspaces', fn (Builder $w) => $w->whereIn('workspaces.id', $workspaceIds))
            ->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            })
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get();

        return [
            'meetings' => $meetings,
            'tasks' => $tasks,
            'transcript_meetings' => $transcriptMeetings,
            'summary_meetings' => $summaryMeetings,
            'users' => $users,
        ];
    }
}
