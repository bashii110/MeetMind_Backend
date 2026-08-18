<?php

namespace Tests\Feature\Meeting;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * User::factory() alone doesn't get a workspace — that only happens via
     * AuthService::register (see WorkspaceService). Tests that need a real
     * signup flow can hit /api/v1/auth/register directly; this helper is
     * for tests that just need a user + workspace to already exist.
     */
    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_registering_creates_a_personal_workspace(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'ali@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspaces', ['owner_id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', ['user_id' => $user->id, 'role' => 'owner']);
    }

    public function test_a_user_can_create_a_meeting_in_their_workspace(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/meetings', [
            'workspace_id' => $workspace->id,
            'title' => 'Q3 Planning',
            'date' => '2026-09-01',
            'time' => '14:00',
            'priority' => 'high',
            'tags' => ['planning', 'q3'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Q3 Planning')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('meetings', ['title' => 'Q3 Planning', 'workspace_id' => $workspace->id]);
    }

    public function test_a_user_cannot_create_a_meeting_in_a_workspace_they_do_not_belong_to(): void
    {
        [$user] = $this->userWithWorkspace();
        $otherWorkspace = Workspace::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/meetings', [
            'workspace_id' => $otherWorkspace->id,
            'title' => 'Not allowed',
            'date' => '2026-09-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_the_meeting_list_is_scoped_to_the_users_workspaces(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [$otherUser, $otherWorkspace] = $this->userWithWorkspace();

        Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Mine']);
        Meeting::factory()->for($otherWorkspace)->for($otherUser, 'owner')->create(['title' => 'Not mine']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/meetings');

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame('Mine', $response->json('data.items.0.title'));
    }

    public function test_only_the_owner_or_workspace_admin_can_update_a_meeting(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($owner, 'owner')->create();

        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/meetings/{$meeting->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_meeting_status_transitions_follow_the_allowed_graph(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['status' => 'draft']);

        // draft -> completed is not allowed (must go through scheduled).
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}/status", ['status' => 'completed'])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}/status", ['status' => 'scheduled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/meetings/{$meeting->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_inviting_a_participant_creates_a_notification_for_them(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($owner, 'owner')->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $response = $this->actingAs($owner, 'sanctum')->postJson(
            "/api/v1/meetings/{$meeting->id}/participants",
            ['emails' => ['invitee@example.com', 'unknown@example.com']],
        );

        $response->assertOk()
            ->assertJsonPath('data.not_found_emails.0', 'unknown@example.com')
            ->assertJsonCount(1, 'data.meeting.participants');

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'invite_status' => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $invitee->id,
            'type' => 'meeting_invitation',
        ]);
    }

    public function test_an_invited_participant_can_accept_the_invitation(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($owner, 'owner')->create();
        $invitee = User::factory()->create();

        $meeting->participants()->create(['user_id' => $invitee->id, 'invite_status' => 'pending']);

        $this->actingAs($invitee, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/participants/respond", ['status' => 'accepted'])
            ->assertOk();

        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'invite_status' => 'accepted',
        ]);
    }
}
