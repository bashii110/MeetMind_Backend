<?php

namespace Tests\Feature\Search;

use App\Enums\WorkspaceRole;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_search_finds_a_matching_meeting_by_title(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Quarterly Budget Review']);
        Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Design Standup']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=Budget');

        $response->assertOk()
            ->assertJsonCount(1, 'data.meetings')
            ->assertJsonPath('data.meetings.0.title', 'Quarterly Budget Review');
    }

    public function test_search_finds_a_matching_task_by_title(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        Task::factory()->for($workspace)->for($user, 'creator')->create(['title' => 'Fix the login bug']);
        Task::factory()->for($workspace)->for($user, 'creator')->create(['title' => 'Write onboarding docs']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=login');

        $response->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.title', 'Fix the login bug');
    }

    public function test_search_finds_a_meeting_by_transcript_content(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Client Call']);
        Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Unrelated']);

        $audioFile = AudioFile::query()->create([
            'meeting_id' => $meeting->id,
            'uploaded_by' => $user->id,
            'status' => 'summarized',
            'extension' => 'm4a',
        ]);

        Transcript::query()->create([
            'meeting_id' => $meeting->id,
            'audio_file_id' => $audioFile->id,
            'text' => 'We discussed migrating to the new payment gateway provider.',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=payment gateway');

        $response->assertOk()
            ->assertJsonCount(1, 'data.transcripts')
            ->assertJsonPath('data.transcripts.0.id', $meeting->id);
    }

    public function test_search_excludes_results_from_workspaces_the_user_does_not_belong_to(): void
    {
        [$user] = $this->userWithWorkspace();
        [$otherUser, $otherWorkspace] = $this->userWithWorkspace();
        Meeting::factory()->for($otherWorkspace)->for($otherUser, 'owner')->create(['title' => 'Secret Budget Meeting']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=Budget');

        $response->assertOk()->assertJsonCount(0, 'data.meetings');
    }

    public function test_query_must_be_at_least_two_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/search?q=a')
            ->assertStatus(422);
    }
}
