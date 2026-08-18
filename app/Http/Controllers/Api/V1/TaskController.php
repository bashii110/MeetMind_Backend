<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\AssignTaskRequest;
use App\Http\Requests\Task\StoreTaskAttachmentRequest;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskProgressRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Http\Resources\TaskAttachmentResource;
use App\Http\Resources\TaskCommentResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskService $tasks) {}

    /** DESIGN.md 3.7: Kanban board — workspace-wide, filterable. */
    public function index(Request $request): JsonResponse
    {
        $tasks = $this->tasks->listForUser(
            $request->user(),
            $request->only(['status', 'priority', 'meeting_id', 'assigned_to_me', 'search']),
            (int) $request->integer('per_page', 20),
        );

        return $this->success([
            'items' => TaskResource::collection($tasks->items()),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $this->resolveWorkspace($user, $request->integer('workspace_id'));

        if (! $workspace) {
            return $this->error('You do not belong to that workspace.', 403);
        }

        $task = $this->tasks->create($user, $workspace, $request->validated());

        return $this->success(new TaskResource($task), 'Task created.', 201);
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load(['creator', 'assignee', 'meeting', 'comments.user', 'attachments.uploadedBy']);

        return $this->success(new TaskResource($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->tasks->update($task, $request->validated());

        return $this->success(new TaskResource($task), 'Task updated.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $this->tasks->delete($task);

        return $this->success(null, 'Task deleted.');
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->tasks->updateStatus($task, TaskStatus::from($request->string('status')->toString()));

        return $this->success(new TaskResource($task), 'Task status updated.');
    }

    public function updateProgress(UpdateTaskProgressRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $task = $this->tasks->updateProgress($task, $request->integer('progress'));

        return $this->success(new TaskResource($task), 'Progress updated.');
    }

    public function assign(AssignTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('assign', $task);

        $assignee = $request->filled('assigned_user_id') ? User::findOrFail($request->integer('assigned_user_id')) : null;
        $task = $this->tasks->assign($task, $assignee, $request->user());

        return $this->success(new TaskResource($task), 'Task assignment updated.');
    }

    public function storeComment(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comment = $this->tasks->addComment($task, $request->user(), $request->string('comment')->toString());
        $comment->load('user');

        return $this->success(new TaskCommentResource($comment), 'Comment added.', 201);
    }

    public function destroyComment(Request $request, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('view', $task);

        if ($comment->user_id !== $request->user()->id) {
            return $this->error('You can only delete your own comments.', 403);
        }

        $this->tasks->deleteComment($comment);

        return $this->success(null, 'Comment deleted.');
    }

    public function storeAttachment(StoreTaskAttachmentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $attachment = $this->tasks->addAttachment($task, $request->user(), $request->file('file'));
        $attachment->load('uploadedBy');

        return $this->success(new TaskAttachmentResource($attachment), 'Attachment uploaded.', 201);
    }

    public function destroyAttachment(Task $task, TaskAttachment $attachment): JsonResponse
    {
        $this->authorize('update', $task);

        $this->tasks->removeAttachment($attachment);

        return $this->success(null, 'Attachment removed.');
    }

    private function resolveWorkspace(User $user, ?int $workspaceId): ?Workspace
    {
        if ($workspaceId) {
            return $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        }

        return $user->workspaces()->first();
    }
}
