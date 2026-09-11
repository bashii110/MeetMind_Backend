<?php

namespace Tests\Feature\Meeting;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingSyncTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_creating_a_meeting_twice_with_the_same_client_ref_is_idempotent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $clientRef = (string) Str::uuid();

        $payload = [
            'workspace_id' => $workspace->id,
            'client_ref' => $clientRef,
            'title' => 'Offline-created meeting',
            'date' => '2026-09-01',
        ];

        $firstId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/meetings', $payload)
            ->assertCreated()->json('data.id');

        $secondId = $this->actingAs($user, 'sanctum')->postJson('/api/v1/meetings', $payload)
            ->assertCreated()->json('data.id');

        $this->assertSame($firstId, $secondId);
        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_updating_a_meeting_with_a_stale_client_updated_at_returns_a_conflict(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Original']);
        $meeting->forceFill(['updated_at' => now()->subMinutes(5)])->save();

        $staleClientUpdatedAt = $meeting->updated_at->toIso8601String();

        // Someone (or another device) changes it first.
        $meeting->update(['title' => 'Changed elsewhere']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/meetings/{$meeting->id}", [
            'title' => 'My offline edit',
            'client_updated_at' => $staleClientUpdatedAt,
        ]);

        $response->assertStatus(409)->assertJsonPath('data.title', 'Changed elsewhere');

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'title' => 'Changed elsewhere']);
    }

    public function test_updating_a_meeting_with_a_current_client_updated_at_succeeds(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Original']);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/meetings/{$meeting->id}", [
            'title' => 'Updated while still in sync',
            'client_updated_at' => $meeting->updated_at->toIso8601String(),
        ]);

        $response->assertOk()->assertJsonPath('data.title', 'Updated while still in sync');
    }

    public function test_deleting_a_meeting_soft_deletes_it_rather_than_removing_the_row(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/meetings/{$meeting->id}")->assertOk();

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/meetings')
            ->assertJsonCount(0, 'data.items');
    }
}
