<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_a_user_can_report_a_task_and_an_admin_can_review_it(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);

        $reportId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'reason' => 'Inappropriate content',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('reported_content', [
            'id' => $reportId,
            'status' => 'pending',
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/reports?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.subject_type', 'Task');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/reports/{$reportId}", [
                'status' => 'dismissed',
                'notes' => 'Reviewed, no action needed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $this->assertDatabaseHas('reported_content', [
            'id' => $reportId,
            'status' => 'dismissed',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_a_stranger_cannot_report_a_task_they_cannot_view(): void
    {
        [, $workspace] = $this->userWithWorkspace();
        $owner = $workspace->owner;
        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')->postJson('/api/v1/reports', [
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'reason' => 'spam',
        ])->assertStatus(403);
    }

    public function test_a_regular_user_cannot_access_the_moderation_queue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/reports')
            ->assertStatus(403);
    }
}
