<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskCandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ConfirmTaskCandidateRequest;
use App\Http\Resources\TaskCandidateResource;
use App\Http\Resources\TaskResource;
use App\Models\TaskCandidate;
use App\Services\TaskService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaskCandidateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskService $tasks) {}

    /** FR-6.3: review/edit an AI suggestion, then create it as a real Task. */
    public function confirm(ConfirmTaskCandidateRequest $request, TaskCandidate $taskCandidate): JsonResponse
    {
        $this->authorize('view', $taskCandidate->meeting);

        if ($taskCandidate->status !== TaskCandidateStatus::Pending) {
            return $this->error('This suggestion has already been confirmed or dismissed.', 422);
        }

        $task = $this->tasks->confirmCandidate($taskCandidate, $request->user(), $request->validated());

        return $this->success(new TaskResource($task), 'Task created from suggestion.', 201);
    }

    public function dismiss(TaskCandidate $taskCandidate): JsonResponse
    {
        $this->authorize('view', $taskCandidate->meeting);

        $taskCandidate->update(['status' => TaskCandidateStatus::Dismissed->value]);

        return $this->success(new TaskCandidateResource($taskCandidate), 'Suggestion dismissed.');
    }
}
