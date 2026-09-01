<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchRequest;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\UserResource;
use App\Services\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** FR-12.1: search across meetings, transcripts, tasks, users, tags, and summaries. */
class SearchController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SearchService $search) {}

    public function index(SearchRequest $request): JsonResponse
    {
        $results = $this->search->search($request->user(), $request->string('q')->toString());

        return $this->success([
            'meetings' => MeetingResource::collection($results['meetings']),
            'tasks' => TaskResource::collection($results['tasks']),
            'transcripts' => MeetingResource::collection($results['transcript_meetings']),
            'summaries' => MeetingResource::collection($results['summary_meetings']),
            'users' => UserResource::collection($results['users']),
        ]);
    }
}
