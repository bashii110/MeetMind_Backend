<?php

namespace App\Services;

use App\Enums\TaskCandidateStatus;
use App\Enums\TaskStatus;
use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskCandidate;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly TaskCommentRepositoryInterface $comments,
        private readonly TaskAttachmentRepositoryInterface $attachments,
    ) {}

    public function listForUser(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->tasks->forUser($user, $filters, $perPage);
    }

    public function create(User $creator, Workspace $workspace, array $data): Task
    {
        $task = $this->tasks->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
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

        return $task->fresh(['assignee', 'creator']);
    }

    public function update(Task $task, array $data): Task
    {
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

    public function updateStatus(Task $task, TaskStatus $status): Task
    {
        $task = $this->tasks->update($task, ['status' => $status->value]);

        if ($status === TaskStatus::Completed) {
            event(new TaskCompleted($task));
        }

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

    public function assign(Task $task, ?User $assignee, User $assignedBy): Task
    {
        $task = $this->tasks->update($task, ['assigned_user_id' => $assignee?->id]);

        if ($assignee) {
            event(new TaskAssigned($task, $assignedBy));
        }

        return $task;
    }

    public function addComment(Task $task, User $author, string $comment): TaskComment
    {
        return $this->comments->create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'comment' => $comment,
        ]);
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
     * human reviewing it can correct the AI before confirming.
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
}
