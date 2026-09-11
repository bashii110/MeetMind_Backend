<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\TaskCandidateStatus;
use App\Enums\TaskStatus;
use App\Events\CommentMentioned;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Exceptions\SyncConflictException;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskCandidate;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskCommentRepositoryInterface $comments,
        private readonly TaskAttachmentRepositoryInterface $attachments,
        private readonly ActivityLogService $activity,
    ) {}

    public function listForUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->tasks->forUser($user, $filters, $perPage);
    }

    /**
     * @param array{client_ref?: ?string} $data
     */
    public function create(User $creator, Workspace $workspace, array $data): Task
    {
        // Phase 10 idempotency — see the identical block's comment in
        // update(): a client_ref that already exists means this
        // offline-queued "create" already landed on a prior attempt.
        if (! empty($data['client_ref'])) {
            $existing = $this->tasks->findByClientRef($workspace, $data['client_ref']);
            if ($existing) {
                return $existing->fresh(['assignee', 'creator']);
            }
        }

        // Phase 11 hardening: StoreTaskRequest/ConfirmTaskCandidateRequest
        // only check that `meeting_id`/`assigned_user_id` reference *some*
        // real row (`exists:meetings,id` / `exists:users,id`) — neither
        // confirms the row belongs to *this* workspace. Without this,
        // a workspace member could link a task to a meeting from a
        // workspace they have no access to (leaking that meeting's title
        // via TaskResource::meeting_title), or assign a task to a user
        // who isn't even a member. Enforced here, centrally, so every
        // caller (direct create, AI task-candidate confirmation) is
        // covered without duplicating the check per entry point.
        $this->assertMeetingBelongsToWorkspace($workspace, $data['meeting_id'] ?? null);
        $this->assertAssigneeIsWorkspaceMember($workspace, $data['assigned_user_id'] ?? null);

        $task = $this->tasks->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'client_ref' => $data['client_ref'] ?? null,
            'meeting_id' => $data['meeting_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'pending',
            'deadline' => $data['deadline'] ?? null,
        ]);

        if ($task->assigned_user_id) {
            event(new TaskAssigned($task, $creator));
        }

        $this->activity->log($workspace, $creator, ActivityAction::TaskCreated, $task, [
            'task_title' => $task->title,
        ]);

        return $task->fresh(['assignee', 'creator']);
    }

    /**
     * @param array{client_updated_at?: ?string} $data
     *
     * @throws SyncConflictException if `client_updated_at` no longer matches the record
     * @throws ValidationException if `meeting_id` doesn't belong to the task's workspace
     */
    public function update(Task $task, array $data): Task
    {
        $this->guardAgainstConflict($task, $data);
        $this->assertMeetingBelongsToWorkspace($task->workspace, $data['meeting_id'] ?? null);

        return $this->tasks->update($task, collect($data)
            ->only(['title', 'description', 'priority', 'deadline', 'meeting_id'])
            ->toArray());
    }

    public function delete(Task $task): void
    {
        foreach ($task->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $this->tasks->delete($task);
    }

    public function updateStatus(Task $task, TaskStatus $status, User $actor): Task
    {
        $task = $this->tasks->update($task, ['status' => $status->value]);

        if ($status === TaskStatus::Completed) {
            event(new TaskCompleted($task));
        }

        $this->activity->log($task->workspace, $actor, ActivityAction::TaskStatusChanged, $task, [
            'task_title' => $task->title,
            'status' => $status->value,
        ]);

        return $task;
    }

    public function updateProgress(Task $task, int $progress): Task
    {
        // Deliberately doesn't auto-flip status at 100% — progress and
        // status are independent fields (FR-7.2 vs FR-7.5); forcing one
        // from the other would surprise a user who wants to review before
        // marking something Completed.
        return $this->tasks->update($task, ['progress' => max(0, min(100, $progress))]);
    }

    /**
     * @throws ValidationException if $assignee isn't a member of the task's workspace
     */
    public function assign(Task $task, ?User $assignee, User $assignedBy): Task
    {
        $this->assertAssigneeIsWorkspaceMember($task->workspace, $assignee?->id);

        $task = $this->tasks->update($task, ['assigned_user_id' => $assignee?->id]);

        if ($assignee) {
            event(new TaskAssigned($task, $assignedBy));

            $this->activity->log($task->workspace, $assignedBy, ActivityAction::TaskAssigned, $task, [
                'task_title' => $task->title,
                'assignee_name' => $assignee->name,
            ]);
        }

        return $task;
    }

    /**
     * @param array<int> $mentionedUserIds — user IDs the frontend's
     *   @mention autocomplete resolved while composing the comment.
     *   Deliberately not parsed out of the comment text server-side: the
     *   autocomplete UI already knows exactly which user was selected, so
     *   trusting that is far more reliable than re-detecting "@Name"
     *   substrings against a member list that may contain ambiguous or
     *   partial name matches. StoreTaskCommentRequest validates every ID
     *   is a real member of this task's workspace.
     */
    public function addComment(Task $task, User $author, string $comment, array $mentionedUserIds = []): TaskComment
    {
        $taskComment = $this->comments->create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'comment' => $comment,
        ]);

        $mentioned = User::query()->whereIn('id', array_unique($mentionedUserIds))->get();

        foreach ($mentioned as $mentionedUser) {
            event(new CommentMentioned($task, $taskComment, $mentionedUser, $author));

            $this->activity->log($task->workspace, $author, ActivityAction::CommentMention, $taskComment, [
                'task_title' => $task->title,
                'mentioned_name' => $mentionedUser->name,
            ]);
        }

        return $taskComment;
    }

    public function deleteComment(TaskComment $comment): void
    {
        $this->comments->delete($comment);
    }

    public function addAttachment(Task $task, User $uploader, UploadedFile $file): TaskAttachment
    {
        $path = $file->store("task-attachments/{$task->id}", 'local');

        return $this->attachments->create([
            'task_id' => $task->id,
            'uploaded_by' => $uploader->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    public function removeAttachment(TaskAttachment $attachment): void
    {
        Storage::disk('local')->delete($attachment->file_path);
        $this->attachments->delete($attachment);
    }

    /**
     * FR-6.3: turn an AI-suggested candidate into a real, manageable Task.
     * Any field in $overrides replaces the candidate's suggestion — the
     * human reviewing it can correct the AI before confirming. Routes
     * through create() above, so the same workspace-membership/meeting
     * guards apply automatically to an admin-overridden assignee too.
     */
    public function confirmCandidate(TaskCandidate $candidate, User $confirmedBy, array $overrides = []): Task
    {
        $task = $this->create($confirmedBy, $candidate->meeting->workspace, [
            'meeting_id' => $candidate->meeting_id,
            'title' => $overrides['title'] ?? $candidate->title,
            'description' => $overrides['description'] ?? $candidate->description,
            'priority' => $overrides['priority'] ?? $candidate->suggested_priority,
            'deadline' => $overrides['deadline'] ?? $candidate->suggested_deadline,
            'assigned_user_id' => $overrides['assigned_user_id'] ?? $candidate->suggested_assignee_user_id,
        ]);

        $candidate->update([
            'status' => TaskCandidateStatus::Confirmed->value,
            'confirmed_task_id' => $task->id,
        ]);

        return $task;
    }

    /**
     * Phase 10 optimistic concurrency — see MeetingService::
     * guardAgainstConflict() for the identical reasoning. Task edits are
     * the case ARCHITECTURE.md 2.3 calls out by name ("a manual merge
     * prompt for conflicting task edits").
     */
    private function guardAgainstConflict(Task $task, array $data): void
    {
        if (empty($data['client_updated_at'])) {
            return;
        }

        $clientKnownAt = Carbon::parse($data['client_updated_at'])->startOfSecond();
        $serverAt = $task->updated_at->copy()->startOfSecond();

        if (! $serverAt->equalTo($clientKnownAt)) {
            throw new SyncConflictException($task);
        }
    }

    /**
     * Phase 11 hardening — see the comment in create() for why this
     * exists. A no-op when $meetingId is null (a manually-created task
     * with no linked meeting, or an update that doesn't touch meeting_id).
     */
    private function assertMeetingBelongsToWorkspace(Workspace $workspace, ?int $meetingId): void
    {
        if ($meetingId === null) {
            return;
        }

        $belongs = Meeting::query()
            ->where('id', $meetingId)
            ->where('workspace_id', $workspace->id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'meeting_id' => ['The selected meeting does not belong to this workspace.'],
            ]);
        }
    }

    /**
     * Phase 11 hardening — see the comment in create() for why this
     * exists. A no-op when $assignedUserId is null (unassigned).
     */
    private function assertAssigneeIsWorkspaceMember(Workspace $workspace, ?int $assignedUserId): void
    {
        if ($assignedUserId === null) {
            return;
        }

        $isMember = $workspace->members()->where('users.id', $assignedUserId)->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'assigned_user_id' => ['The assignee must be a member of this workspace.'],
            ]);
        }
    }
}
