<?php

namespace App\Jobs;

use App\Enums\AudioFileStatus;
use App\Models\AudioFile;
use App\Services\MeetingSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public string $queue = 'transcription'; // ARCHITECTURE.md 3.5

    public function __construct(public readonly AudioFile $audioFile) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1m, 5m, 15m — ARCHITECTURE.md 3.4
    }

    public function handle(MeetingSummaryService $summaryService): void
    {
        // Idempotent: a retried/duplicated dispatch shouldn't re-transcribe.
        if ($this->audioFile->transcript()->exists()) {
            $this->dispatchNext();

            return;
        }

        $this->audioFile->update(['status' => AudioFileStatus::Transcribing->value]);

        $transcript = $summaryService->transcribeAudio($this->audioFile);

        $this->audioFile->update(['status' => AudioFileStatus::Transcribed->value]);

        GenerateSummaryJob::dispatch($transcript);
    }

    private function dispatchNext(): void
    {
        $transcript = $this->audioFile->transcript;
        if ($transcript && ! $transcript->summary) {
            GenerateSummaryJob::dispatch($transcript);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->audioFile->update([
            'status' => AudioFileStatus::Failed->value,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
