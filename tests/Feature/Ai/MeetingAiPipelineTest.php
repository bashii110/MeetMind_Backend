<?php

namespace Tests\Feature\Ai;

use App\Enums\WorkspaceRole;
use App\Jobs\TranscribeAudioJob;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingAiPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function meetingWithUploadedAudio(): array
    {
        $user = User::factory()->create(['name' => 'Ali Khan']);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();

        Storage::fake('local');
        $path = "audio/{$meeting->id}/1.m4a";
        Storage::disk('local')->put($path, 'fake-audio-bytes');

        $audioFile = AudioFile::query()->create([
            'meeting_id' => $meeting->id,
            'uploaded_by' => $user->id,
            'status' => 'uploaded',
            'extension' => 'm4a',
            'path' => $path,
            'total_chunks' => 1,
            'received_chunks' => [0],
        ]);

        return [$user, $meeting, $audioFile];
    }

    public function test_the_full_pipeline_runs_end_to_end_and_produces_a_summary_and_task_candidates(): void
    {
        [$user, $meeting, $audioFile] = $this->meetingWithUploadedAudio();

        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'Ali will finish the login screen by Friday. We decided to launch in Q3. Budget is a risk.',
                'language' => 'english',
            ], 200),
            'api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'executive_summary' => 'The team aligned on a Q3 launch and assigned the login screen to Ali.',
                            'bullet_summary' => ['Q3 launch agreed', 'Login screen assigned to Ali'],
                            'decisions' => ['Launch in Q3'],
                            'risks' => ['Budget constraints'],
                            'next_steps' => ['Ali finishes login screen'],
                            'deadlines' => ['Friday: login screen'],
                            'mood' => 'positive',
                        ])],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => ['content' => json_encode([
                            'tasks' => [[
                                'title' => 'Finish login screen',
                                'description' => 'Complete the login screen implementation.',
                                'suggested_assignee_name' => 'Ali Khan',
                                'suggested_deadline' => now()->addDays(3)->toDateString(),
                                'suggested_priority' => 'high',
                            ]],
                        ])],
                    ]],
                ], 200),
        ]);

        TranscribeAudioJob::dispatch($audioFile);

        $audioFile->refresh();
        $this->assertSame('summarized', $audioFile->status->value);
        $this->assertNull($audioFile->error_message);

        $this->assertDatabaseHas('transcripts', ['meeting_id' => $meeting->id]);
        $this->assertDatabaseHas('summaries', [
            'meeting_id' => $meeting->id,
            'mood' => 'positive',
        ]);
        $this->assertDatabaseHas('task_candidates', [
            'meeting_id' => $meeting->id,
            'title' => 'Finish login screen',
            'suggested_assignee_user_id' => $user->id, // matched by name
        ]);

        // Endpoints reflect the finished pipeline.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}/ai-status")
            ->assertOk()
            ->assertJsonPath('data.status', 'summarized');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.mood', 'positive');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}/task-candidates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.suggested_assignee.name', 'Ali Khan');
    }

    public function test_a_failed_job_marks_the_audio_file_failed_with_the_error_message(): void
    {
        [, , $audioFile] = $this->meetingWithUploadedAudio();

        // Testing failed() directly, rather than dispatching and letting
        // real retry/backoff exhaust, since the `sync` queue connection
        // (used in tests) runs a job once and lets exceptions propagate
        // immediately — it doesn't replicate a real worker's tries/backoff
        // bookkeeping. failed() is exactly what a real worker calls once
        // $tries is exhausted, so this tests the behavior that matters.
        $job = new TranscribeAudioJob($audioFile);
        $job->failed(new \RuntimeException('OpenAI request failed.'));

        $audioFile->refresh();
        $this->assertSame('failed', $audioFile->status->value);
        $this->assertSame('OpenAI request failed.', $audioFile->error_message);
    }

    public function test_a_stranger_cannot_see_another_workspaces_meeting_summary(): void
    {
        [, $meeting] = $this->meetingWithUploadedAudio();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/meetings/{$meeting->id}/summary")
            ->assertStatus(403);
    }
}
