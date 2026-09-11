<?php

namespace Tests\Feature\Security;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for a validation gap found in the Phase 11 review:
 * StoreTaskRequest/AssignTaskRequest only checked that `assigned_user_id`
 * / `meeting_id` referenced *some* real row (`exists:users,id` /
 * `exists:meetings,id`), never that the row belonged to the task's own
 * workspace. See the fix in TaskService::assertAssigneeIsWorkspaceMember()
 * / assertMeetingBelongsToWorkspace().
 */
class TaskAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_a_task_cannot_be_created_assigned_to_a_non_member_of_the_workspace(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $outsider = User::factory()->create();

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/tasks', [
            'workspace_id' => $workspace->id,
            'title' => 'Sneaky assignment',
            'assigned_user_id' => $outsider->id,
        ])->assertStatus(422)->assertJsonValidationErrors('assigned_user_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'Sneaky assignment']);
    }

    public function test_a_task_cannot_be_assigned_to_a_non_member_via_the_assign_endpoint(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();
        $outsider = User::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/assign", ['assigned_user_id' => $outsider->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('assigned_user_id');
    }

    public function test_a_task_cannot_reference_a_meeting_from_a_different_workspace(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        [$otherOwner, $otherWorkspace] = $this->userWithWorkspace();
        $foreignMeeting = Meeting::factory()->for($otherWorkspace)->for($otherOwner, 'owner')->create();

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/tasks', [
            'workspace_id' => $workspace->id,
            'title' => 'Cross-workspace link attempt',
            'meeting_id' => $foreignMeeting->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('meeting_id');
    }

    public function test_a_task_cannot_be_relinked_to_a_meeting_from_a_different_workspace_on_update(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();

        [$otherOwner, $otherWorkspace] = $this->userWithWorkspace();
        $foreignMeeting = Meeting::factory()->for($otherWorkspace)->for($otherOwner, 'owner')->create();

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", ['meeting_id' => $foreignMeeting->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('meeting_id');
    }
}
