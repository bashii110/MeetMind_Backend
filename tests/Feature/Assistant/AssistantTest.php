<?php

namespace Tests\Feature\Assistant;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_a_meeting_member_can_ask_the_assistant_a_question(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create(['title' => 'Roadmap Sync']);
        Task::factory()->for($workspace)->for($user, 'creator')->create([
            'meeting_id' => $meeting->id,
            'title' => 'Ship the export feature',
            'assigned_user_id' => $user->id,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'The pending task is "Ship the export feature," owned by '.$user->name.'.']],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/meetings/{$meeting->id}/assistant/query",
            ['query' => 'Who owns the pending task?'],
        );

        $response->assertOk()->assertJsonPath(
            'data.answer',
            'The pending task is "Ship the export feature," owned by '.$user->name.'.',
        );

        Http::assertSent(function ($request) use ($meeting) {
            $body = $request->body();

            return str_contains($body, $meeting->title) && str_contains($body, 'Who owns the pending task?');
        });
    }

    public function test_a_stranger_cannot_query_the_assistant_for_someone_elses_meeting(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($owner, 'owner')->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/assistant/query", ['query' => 'Summarize this.'])
            ->assertStatus(403);
    }

    public function test_query_is_required(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/assistant/query", [])
            ->assertStatus(422);
    }
}
