<?php

namespace App\Services;

use App\Enums\ContentReportStatus;
use App\Models\Meeting;
use App\Models\ReportedContent;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Repositories\Contracts\ReportedContentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * FR-16.3: the content-moderation queue. Reporting is open to any
 * authenticated user (subject to being able to view the thing they're
 * reporting); resolving a report is restricted to system admins at the
 * route level (see routes/api.php's 'system_admin' middleware group).
 */
class ModerationService
{
    /**
     * Whitelisted subject types a report can target — deliberately not
     * accepting a raw class name from the client, so a report request can
     * never be used to attach itself to an arbitrary Eloquent model.
     *
     * @var array<string, class-string>
     */
    private const SUBJECT_MAP = [
        'task' => Task::class,
        'meeting' => Meeting::class,
        'task_comment' => TaskComment::class,
    ];

    public function __construct(private readonly ReportedContentRepositoryInterface $reports) {}

    /**
     * @param array{subject_type: string, subject_id: int, reason: string, details?: ?string} $data
     */
    public function report(User $reporter, array $data): ReportedContent
    {
        $modelClass = self::SUBJECT_MAP[$data['subject_type']];
        $subject = $modelClass::findOrFail($data['subject_id']);

        // Reporting requires the same visibility the reporter would need
        // to view the content in the first place — a stranger can't
        // discover and report content they can't otherwise see.
        match (true) {
            $subject instanceof Task => Gate::forUser($reporter)->authorize('view', $subject),
            $subject instanceof Meeting => Gate::forUser($reporter)->authorize('view', $subject),
            $subject instanceof TaskComment => Gate::forUser($reporter)->authorize('view', $subject->task),
            default => null,
        };

        $workspaceId = match (true) {
            $subject instanceof Task => $subject->workspace_id,
            $subject instanceof Meeting => $subject->workspace_id,
            $subject instanceof TaskComment => $subject->task->workspace_id,
            default => null,
        };

        return $this->reports->create([
            'workspace_id' => $workspaceId,
            'reported_by' => $reporter->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => ContentReportStatus::Pending->value,
        ]);
    }

    /**
     * @param array{status?: string} $filters
     */
    public function listForAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->reports->paginateFiltered($filters, $perPage);
    }

    public function resolve(ReportedContent $report, User $admin, ContentReportStatus $status, ?string $notes): ReportedContent
    {
        return $this->reports->update($report, [
            'status' => $status->value,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }
}
