<?php

namespace App\Services;

use App\Enums\AudioFileStatus;
use App\Jobs\TranscribeAudioJob;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\User;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Chunks are written to a temp per-upload directory as they arrive
 * (named by index, so re-sending the same chunk after a retry just
 * overwrites it — idempotent). Once every expected chunk is present,
 * `complete()` concatenates them in order into the final file.
 *
 * NOTE: uses the 'local' disk's absolute path directly (fopen/stream_copy)
 * for efficient concatenation of potentially large files. This only works
 * for the local driver — swapping to S3 in production (ARCHITECTURE.md 7)
 * would need a different completion strategy (e.g. S3 multipart upload).
 */
class AudioUploadService
{
    public function __construct(private readonly AudioFileRepositoryInterface $audioFiles) {}

    public function init(Meeting $meeting, User $user, array $data): AudioFile
    {
        return $this->audioFiles->create([
            'meeting_id' => $meeting->id,
            'uploaded_by' => $user->id,
            'status' => AudioFileStatus::Uploading->value,
            'mime_type' => $data['mime_type'] ?? null,
            'extension' => $data['extension'] ?? 'm4a',
            'total_size' => $data['total_size'] ?? null,
            'total_chunks' => $data['total_chunks'],
            'received_chunks' => [],
            'duration_seconds' => $data['duration_seconds'] ?? null,
        ]);
    }

    /**
     * @return array{received_chunks: array<int>, total_chunks: int}
     */
    public function storeChunk(AudioFile $audioFile, int $index, UploadedFile $chunk): array
    {
        if ($audioFile->total_chunks !== null && $index >= $audioFile->total_chunks) {
            throw ValidationException::withMessages([
                'chunk_index' => ["Chunk index {$index} is out of range (total_chunks={$audioFile->total_chunks})."],
            ]);
        }

        Storage::disk('local')->putFileAs($this->chunkDir($audioFile), $chunk, (string) $index);

        $received = $audioFile->received_chunks ?? [];
        if (! in_array($index, $received, true)) {
            $received[] = $index;
            sort($received);
            $audioFile->update(['received_chunks' => $received]);
        }

        return ['received_chunks' => $received, 'total_chunks' => (int) $audioFile->total_chunks];
    }

    /**
     * Lets the client ask "what do you already have?" after a dropped
     * connection or app restart, so it only re-sends what's missing.
     *
     * @return array{received_chunks: array<int>, total_chunks: int}
     */
    public function status(AudioFile $audioFile): array
    {
        return [
            'received_chunks' => $audioFile->received_chunks ?? [],
            'total_chunks' => (int) $audioFile->total_chunks,
        ];
    }

    public function complete(AudioFile $audioFile): AudioFile
    {
        $expected = range(0, $audioFile->total_chunks - 1);
        $received = $audioFile->received_chunks ?? [];

        if (array_diff($expected, $received) !== []) {
            throw ValidationException::withMessages([
                'chunks' => ['Not all chunks have been received yet.'],
            ]);
        }

        $disk = Storage::disk('local');
        $finalDir = "audio/{$audioFile->meeting_id}";
        $disk->makeDirectory($finalDir);
        $finalPath = "{$finalDir}/{$audioFile->id}.{$audioFile->extension}";

        $out = fopen($disk->path($finalPath), 'wb');
        if ($out === false) {
            throw new RuntimeException('Could not open destination file for writing.');
        }

        try {
            for ($i = 0; $i < $audioFile->total_chunks; $i++) {
                $in = fopen($disk->path($this->chunkDir($audioFile)."/{$i}"), 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        $disk->deleteDirectory($this->chunkDir($audioFile));

        $audioFile->update([
            'status' => AudioFileStatus::Uploaded->value,
            'path' => $finalPath,
            'total_size' => $disk->size($finalPath),
        ]);

        TranscribeAudioJob::dispatch($audioFile->fresh());

        return $audioFile->fresh();
    }

    public function markFailed(AudioFile $audioFile): void
    {
        $audioFile->update(['status' => AudioFileStatus::Failed->value]);
    }

    private function chunkDir(AudioFile $audioFile): string
    {
        return "audio-chunks/{$audioFile->id}";
    }
}
