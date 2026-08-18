<?php

namespace Tests\Feature\AudioFile;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioUploadTest extends TestCase
{
    use RefreshDatabase;

    private function meetingWithOwner(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();

        return [$user, $meeting];
    }

    public function test_a_full_chunked_upload_concatenates_chunks_in_order(): void
    {
        Storage::fake('local');
        [$user, $meeting] = $this->meetingWithOwner();

        $init = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/meetings/{$meeting->id}/recording/init",
            ['total_chunks' => 3, 'extension' => 'm4a', 'mime_type' => 'audio/m4a'],
        );
        $init->assertCreated();
        $audioFileId = $init->json('data.id');

        // Upload chunks out of order to prove ordering is enforced on complete, not on arrival.
        $this->actingAs($user, 'sanctum')->post("/api/v1/audio-files/{$audioFileId}/chunks", [
            'chunk_index' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('c1', 'BBB'),
        ])->assertOk();

        $this->actingAs($user, 'sanctum')->post("/api/v1/audio-files/{$audioFileId}/chunks", [
            'chunk_index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA'),
        ])->assertOk();

        // Simulate a dropped connection before the last chunk, then resume.
        $status = $this->actingAs($user, 'sanctum')->getJson("/api/v1/audio-files/{$audioFileId}/status");
        $status->assertOk()->assertJsonPath('data.received_chunks', [0, 1]);

        $this->actingAs($user, 'sanctum')->post("/api/v1/audio-files/{$audioFileId}/chunks", [
            'chunk_index' => 2,
            'chunk' => UploadedFile::fake()->createWithContent('c2', 'CCC'),
        ])->assertOk();

        $complete = $this->actingAs($user, 'sanctum')->postJson("/api/v1/audio-files/{$audioFileId}/complete");
        $complete->assertOk()->assertJsonPath('data.status', 'uploaded');

        $this->assertDatabaseHas('audio_files', ['id' => $audioFileId, 'status' => 'uploaded']);

        $path = "audio/{$meeting->id}/{$audioFileId}.m4a";
        Storage::disk('local')->assertExists($path);
        $this->assertSame('AAABBBCCC', Storage::disk('local')->get($path));
    }

    public function test_completing_before_all_chunks_arrive_fails(): void
    {
        Storage::fake('local');
        [$user, $meeting] = $this->meetingWithOwner();

        $audioFileId = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/meetings/{$meeting->id}/recording/init",
            ['total_chunks' => 2],
        )->json('data.id');

        $this->actingAs($user, 'sanctum')->post("/api/v1/audio-files/{$audioFileId}/chunks", [
            'chunk_index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('c0', 'AAA'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/audio-files/{$audioFileId}/complete")
            ->assertStatus(422);
    }

    public function test_a_stranger_cannot_upload_audio_for_someone_elses_meeting(): void
    {
        [, $meeting] = $this->meetingWithOwner();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/meetings/{$meeting->id}/recording/init", ['total_chunks' => 1])
            ->assertStatus(403);
    }
}
