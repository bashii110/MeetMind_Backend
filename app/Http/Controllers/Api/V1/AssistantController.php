<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AssistantQueryRequest;
use App\Models\Meeting;
use App\Services\AssistantService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-11.1/11.2: in-meeting AI chat assistant. One endpoint, one shape —
 * see AssistantService for why "summarize," "who owns Task X," "draft a
 * follow-up email," etc. are all just different free-text `query`
 * values against the same contextualized prompt, rather than separate
 * routes per capability.
 */
class AssistantController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AssistantService $assistant) {}

    public function query(AssistantQueryRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $answer = $this->assistant->ask($meeting, $request->string('query')->toString());

        return $this->success(['answer' => $answer], 'Assistant responded.');
    }
}
