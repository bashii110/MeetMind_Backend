<?php

namespace Tests\Feature\Task;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskSyncTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_creating_a_task_twice_with_the_same_client_ref_is_idempotent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $clientRef = (string) Str::uuid();

        $payload = [
            'workspace_id' => $workspace->id,
            'client_ref' => $clientRef,
            'title' => 'Offline-created task',
        ];

        $firstId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', $payload)
            ->assertCreated()->json('data.id');

        $secondId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', $payload)
            ->assertCreated()->json('data.id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_updating_a_task_with_a_stale_client_updated_at_returns_a_conflict(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create(['title' => 'Original']);
        $task->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $staleClientUpdatedAt = $task->updated_at->toIso8601String();

        $task->update(['title' => 'Changed elsewhere']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'My offline edit',
            'client_updated_at' => $staleClientUpdatedAt,
        ]);

        $response->assertStatus(409)->assertJsonPath('data.title', 'Changed elsewhere');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Changed elsewhere']);
    }

    public function test_updating_a_task_with_a_current_client_updated_at_succeeds(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create(['title' => 'Original']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", [
            'title' => 'Updated while still in sync',
            'client_updated_at' => $task->updated_at->toIso8601String(),
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'Updated while still in sync');
    }

    public function test_deleting_a_task_soft_deletes_it_rather_than_removing_the_row(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/tasks/{$task->id}")->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertJsonCount(0, 'data.items');
    }
}
