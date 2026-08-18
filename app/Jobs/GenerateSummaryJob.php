<?php

namespace App\Jobs;

use App\Enums\AudioFileStatus;
use App\Models\Transcript;
use App\Services\MeetingSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public string $queue = 'ai'; // ARCHITECTURE.md 3.5

    public function __construct(public readonly Transcript $transcript) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MeetingSummaryService $summaryService): void
    {
        $audioFile = $this->transcript->audioFile;
        $audioFile?->update(['status' => AudioFileStatus::Summarizing->value]);

        $meeting = $this->transcript->meeting;

        // Idempotent: skip regenerating if a retried dispatch lands after
        // a summary already exists for this transcript.
        if (! $meeting->summary || $meeting->summary->transcript_id !== $this->transcript->id) {
            $summaryService->generateSummary($meeting, $this->transcript);
        }

        ExtractTasksJob::dispatch($this->transcript);
    }

    public function failed(Throwable $exception): void
    {
        $this->transcript->audioFile?->update([
            'status' => AudioFileStatus::Failed->value,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
