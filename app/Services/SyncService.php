<?php

namespace App\Services;

use App\Http\Resources\MeetingResource;
use App\Http\Resources\TaskResource;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Workspace;
use Carbon\CarbonInterface;

/**
 * FR-15.2 / PHASES.md Phase 10: "Local caching strategy finalized across
 * meetings/tasks (Hive/Drift as source of truth)." This is the single
 * call the Flutter app makes after reconnecting to reconcile its local
 * cache — everything that changed (upserts) and everything that's gone
 * (tombstones, via the soft-deletes added this phase) since the client's
 * last known sync point, for both meetings and tasks in one round trip.
 *
 * Deliberately scoped to meetings + tasks only, matching PHASES.md's
 * exact wording — notifications/other resources can follow the same
 * shape later if the client needs them synced the same way.
 */
class SyncService
{
    /**
     * @return array{
     *   server_time: string,
     *   meetings: array{upserts: mixed, deletes: array<int, int>},
     *   tasks: array{upserts: mixed, deletes: array<int, int>},
     * }
     */
    public function forWorkspace(Workspace $workspace, CarbonInterface $since): array
    {
        // Captured once up front and returned to the client as the new
        // sync cursor for its *next* call, rather than trusting the
        // client's own clock — avoids missed-update bugs from clock skew
        // between the device and the server.
        $serverTime = now();

        $meetingUpserts = Meeting::query()
            ->where('workspace_id', $workspace->id)
            ->where('updated_at', '>', $since)
            ->with(['owner', 'tags', 'participants.user'])
            ->get();

        $meetingDeletes = Meeting::onlyTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('deleted_at', '>', $since)
            ->pluck('id');

        $taskUpserts = Task::query()
            ->where('workspace_id', $workspace->id)
            ->where('updated_at', '>', $since)
            ->with(['assignee', 'creator', 'meeting'])
            ->get();

        $taskDeletes = Task::onlyTrashed()
            ->where('workspace_id', $workspace->id)
            ->where('deleted_at', '>', $since)
            ->pluck('id');

        return [
            'server_time' => $serverTime->toIso8601String(),
            'meetings' => [
                'upserts' => MeetingResource::collection($meetingUpserts),
                'deletes' => $meetingDeletes->values()->all(),
            ],
            'tasks' => [
                'upserts' => TaskResource::collection($taskUpserts),
                'deletes' => $taskDeletes->values()->all(),
            ],
        ];
    }
}
