<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MeetingStatus;
use App\Enums\ParticipantInviteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\InviteParticipantsRequest;
use App\Http\Requests\Meeting\RespondToInvitationRequest;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingStatusRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MeetingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MeetingService $meetings) {}

    /** SRD 3.4: filter chips (status, category, tag) + search bar. */
    public function index(Request $request): JsonResponse
    {
        $meetings = $this->meetings->listForUser(
            $request->user(),
            $request->only(['status', 'category', 'tag', 'search']),
            (int) $request->integer('per_page', 15),
        );

        return $this->success([
            'items' => MeetingResource::collection($meetings->items()),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'total' => $meetings->total(),
            ],
        ]);
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $this->resolveWorkspace($user, $request->integer('workspace_id'));

        if (! $workspace) {
            return $this->error('You do not belong to that workspace.', 403);
        }

        $meeting = $this->meetings->create($user, $workspace, $request->validated());

        return $this->success(new MeetingResource($meeting), 'Meeting created.', 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load(['owner', 'tags', 'participants.user']);

        return $this->success(new MeetingResource($meeting));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting = $this->meetings->update($meeting, $request->validated());

        return $this->success(new MeetingResource($meeting), 'Meeting updated.');
    }

    public function destroy(Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        $this->meetings->delete($meeting);

        return $this->success(null, 'Meeting deleted.');
    }

    public function updateStatus(UpdateMeetingStatusRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting = $this->meetings->changeStatus($meeting, MeetingStatus::from($request->string('status')->toString()));

        return $this->success(new MeetingResource($meeting), 'Meeting status updated.');
    }

    public function inviteParticipants(InviteParticipantsRequest $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manageParticipants', $meeting);

        $result = $this->meetings->invite($meeting, $request->user(), $request->validated('emails'));

        $meeting->load(['owner', 'tags', 'participants.user']);

        return $this->success([
            'meeting' => new MeetingResource($meeting),
            'not_found_emails' => $result['not_found'],
        ], 'Invitations sent.');
    }

    public function removeParticipant(Meeting $meeting, User $user): JsonResponse
    {
        $this->authorize('manageParticipants', $meeting);

        $this->meetings->removeParticipant($meeting, $user);

        return $this->success(null, 'Participant removed.');
    }

    /** The invited user accepting/declining their own invitation. */
    public function respondToInvitation(RespondToInvitationRequest $request, Meeting $meeting): JsonResponse
    {
        $this->meetings->respondToInvitation(
            $meeting,
            $request->user(),
            ParticipantInviteStatus::from($request->string('status')->toString()),
        );

        return $this->success(null, 'Response recorded.');
    }

    private function resolveWorkspace(User $user, ?int $workspaceId): ?Workspace
    {
        if ($workspaceId) {
            return $user->workspaces()->where('workspaces.id', $workspaceId)->first();
        }

        // No workspace_id given — default to the user's first (personal)
        // workspace, since the frontend has no workspace switcher until
        // Phase 7.
        return $user->workspaces()->first();
    }
}
