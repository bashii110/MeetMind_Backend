<?php

namespace Tests\Feature\Analytics;

use App\Enums\WorkspaceRole;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_analytics_returns_meeting_and_task_aggregates_for_the_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['date' => now()->toDateString()]);
        Task::factory()->for($workspace)->for($user, 'creator')->create(['status' => 'completed']);
        Task::factory()->for($workspace)->for($user, 'creator')->create(['status' => 'pending']);

        AudioFile::query()->create([
            'meeting_id' => $meeting->id,
            'uploaded_by' => $user->id,
            'status' => 'summarized',
            'extension' => 'm4a',
            'duration_seconds' => 1800,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/analytics?workspace_id={$workspace->id}");

        $response->assertOk()
            ->assertJsonPath('data.task_completion.completed', 1)
            ->assertJsonPath('data.task_completion.pending', 1)
            ->assertJsonPath('data.avg_meeting_duration_minutes', 30.0)
            ->assertJsonPath('data.total_meetings', 1)
            ->assertJsonStructure(['data' => ['meetings_per_month', 'active_users', 'pending_tasks']]);
    }

    public function test_a_user_cannot_view_analytics_for_a_workspace_they_do_not_belong_to(): void
    {
        [$user] = $this->userWithWorkspace();
        $otherWorkspace = Workspace::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/analytics?workspace_id={$otherWorkspace->id}")
            ->assertStatus(403);
    }

    public function test_productivity_score_endpoint_returns_a_score_and_breakdown(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        Task::factory()->for($workspace)->for($user, 'creator')->create([
            'assigned_user_id' => $user->id,
            'status' => 'completed',
            'deadline' => now()->addDay(),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/analytics/productivity-score');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['score', 'period_start', 'period_end', 'breakdown']]);

        $this->assertGreaterThan(0, $response->json('data.score'));
    }
}
