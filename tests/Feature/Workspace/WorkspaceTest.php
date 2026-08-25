<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        return [$owner, $workspace];
    }

    public function test_a_user_can_create_a_workspace_and_becomes_its_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/workspaces', ['name' => 'Product Team']);

        $response->assertCreated()->assertJsonPath('data.name', 'Product Team');

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $response->json('data.id'),
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_only_a_manager_can_rename_the_workspace(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_a_manager_can_invite_an_existing_user_by_email(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/members", ['email' => 'invitee@example.com', 'role' => 'member'])
            ->assertOk();

        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $invitee->id, 'type' => 'workspace_invitation']);
        $this->assertDatabaseHas('activity_logs', ['workspace_id' => $workspace->id, 'action' => 'member.invited']);
    }

    public function test_inviting_an_unknown_email_fails(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/members", ['email' => 'nobody@example.com'])
            ->assertStatus(422);
    }

    public function test_the_owners_role_cannot_be_changed_or_removed(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $admin = User::factory()->create();
        $workspace->members()->attach($admin->id, ['role' => WorkspaceRole::Admin->value]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}", ['role' => 'member'])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertStatus(422);
    }

    public function test_a_member_can_leave_but_the_owner_cannot(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/leave")
            ->assertOk();
        $this->assertDatabaseMissing('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/leave")
            ->assertStatus(422);
    }

    public function test_the_owner_can_delete_the_workspace(): void
    {
        [$owner, $workspace] = $this->ownerWithWorkspace();

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertOk();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }
}
