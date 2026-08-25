<?php

namespace Tests\Feature\Task;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskMentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentioning_a_workspace_member_in_a_comment_notifies_them(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);
        $mentioned = User::factory()->create();
        $workspace->members()->attach($mentioned->id, ['role' => WorkspaceRole::Member->value]);

        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();

        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/comments", [
            'comment' => 'Can you take a look, @'.$mentioned->name,
            'mentioned_user_ids' => [$mentioned->id],
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', ['user_id' => $mentioned->id, 'type' => 'mention']);
        $this->assertDatabaseHas('activity_logs', ['workspace_id' => $workspace->id, 'action' => 'comment.mention']);
    }

    public function test_mentioning_someone_outside_the_workspace_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);
        $outsider = User::factory()->create();

        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();

        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/comments", [
            'comment' => 'cc @'.$outsider->name,
            'mentioned_user_ids' => [$outsider->id],
        ])->assertStatus(422);
    }
}
