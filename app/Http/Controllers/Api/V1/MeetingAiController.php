<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiStatusResource;
use App\Http\Resources\SummaryResource;
use App\Http\Resources\TaskCandidateResource;
use App\Http\Resources\TranscriptResource;
use App\Models\Meeting;
use App\Repositories\Contracts\TaskCandidateRepositoryInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MeetingAiController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TaskCandidateRepositoryInterface $taskCandidates) {}

    /** FR-5.3: lightweight polling target while transcription/summary run in the background. */
    public function aiStatus(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $latestAudioFile = $meeting->audioFiles()->latest()->first();

        return $this->success(new AiStatusResource([
            'status' => $latestAudioFile?->status?->value ?? 'no_recording',
            'error_message' => $latestAudioFile?->error_message,
        ]));
    }

    public function transcript(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $transcript = $meeting->transcripts()->latest()->first();

        if (! $transcript) {
            return $this->error('Transcript not available yet.', 404);
        }

        return $this->success(new TranscriptResource($transcript));
    }

    public function summary(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        if (! $meeting->summary) {
            return $this->error('Summary not available yet.', 404);
        }

        return $this->success(new SummaryResource($meeting->summary));
    }

    public function taskCandidates(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $candidates = $this->taskCandidates->forMeeting($meeting)->load('suggestedAssignee');

        return $this->success(TaskCandidateResource::collection($candidates));
    }
}
