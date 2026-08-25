<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        return [$owner, $workspace];
    }

    public function test_creating_a_meeting_logs_activity_visible_on_the_workspace_timeline(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/meetings', [
            'workspace_id' => $workspace->id,
            'title' => 'Sprint Planning',
            'date' => '2026-09-01',
        ])->assertCreated();

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/workspaces/{$workspace->id}/activity");

        $response->assertOk()
            ->assertJsonPath('data.items.0.action', 'meeting.created')
            ->assertJsonPath('data.items.0.metadata.meeting_title', 'Sprint Planning');
    }

    public function test_a_stranger_cannot_view_the_activity_timeline(): void
    {
        [, $workspace] = $this->ownerWithWorkspace();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspace->id}/activity")
            ->assertStatus(403);
    }
}
