<?php

namespace Tests\Feature\Sync;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_a_full_sync_with_no_cursor_returns_everything(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        Meeting::factory()->for($workspace)->for($user, 'owner')->create();
        Task::factory()->for($workspace)->for($user, 'creator')->create();

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/sync?workspace_id={$workspace->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.meetings.upserts')
            ->assertJsonCount(1, 'data.tasks.upserts')
            ->assertJsonStructure(['data' => ['server_time']]);
    }

    public function test_an_incremental_sync_only_returns_changes_since_the_cursor_and_reports_deletions(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        // Created well before the cursor, and never touched again — should
        // appear in neither upserts nor deletes.
        $staleMeeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();
        $staleMeeting->forceFill(['updated_at' => now()->subMinutes(10)])->save();

        $staleTask = Task::factory()->for($workspace)->for($user, 'creator')->create();
        $staleTask->forceFill(['updated_at' => now()->subMinutes(10)])->save();

        $cursor = now()->subMinutes(5)->toIso8601String();

        $newMeeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();
        $staleTask->delete(); // soft delete, after the cursor

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/sync?workspace_id={$workspace->id}&since={$cursor}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.meetings.upserts')
            ->assertJsonPath('data.meetings.upserts.0.id', $newMeeting->id)
            ->assertJsonCount(0, 'data.tasks.upserts')
            ->assertJsonPath('data.tasks.deletes.0', $staleTask->id);
    }

    public function test_a_user_cannot_sync_a_workspace_they_do_not_belong_to(): void
    {
        [$user] = $this->userWithWorkspace();
        $otherWorkspace = Workspace::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/sync?workspace_id={$otherWorkspace->id}")
            ->assertStatus(403);
    }
}
