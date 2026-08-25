<?php

namespace Tests\Feature\Calendar;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_it_returns_meetings_and_task_deadlines_within_the_requested_range(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $inRange = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['date' => '2026-09-10']);
        Meeting::factory()->for($workspace)->for($user, 'owner')->create(['date' => '2026-10-15']); // outside range

        $taskInRange = Task::factory()->for($workspace)->for($user, 'creator')
            ->create(['deadline' => '2026-09-12 10:00:00']);
        Task::factory()->for($workspace)->for($user, 'creator')
            ->create(['deadline' => '2026-10-20 10:00:00']); // outside range

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start=2026-09-01&end=2026-09-30');

        $response->assertOk()
            ->assertJsonCount(1, 'data.meetings')
            ->assertJsonCount(1, 'data.task_deadlines')
            ->assertJsonPath('data.meetings.0.id', $inRange->id)
            ->assertJsonPath('data.task_deadlines.0.id', $taskInRange->id);
    }

    public function test_it_excludes_meetings_from_workspaces_the_user_does_not_belong_to(): void
    {
        [$user] = $this->userWithWorkspace();
        [$otherUser, $otherWorkspace] = $this->userWithWorkspace();

        Meeting::factory()->for($otherWorkspace)->for($otherUser, 'owner')->create(['date' => '2026-09-10']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start=2026-09-01&end=2026-09-30');

        $response->assertOk()->assertJsonCount(0, 'data.meetings');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start=2026-09-30&end=2026-09-01')
            ->assertStatus(422);
    }
}
