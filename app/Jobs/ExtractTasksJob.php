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

class ExtractTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

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
        $meeting = $this->transcript->meeting;

        // Idempotent: don't create duplicate candidates on a retried dispatch.
        if ($this->transcript->taskCandidates()->doesntExist()) {
            $summaryService->extractTasks($meeting, $this->transcript);
        }

        // Summary + Task suggestions saved -> status = summarized.
        $this->transcript->audioFile?->update([
            'status' => AudioFileStatus::Summarized->value
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->transcript->audioFile?->update([
            'status' => AudioFileStatus::Failed->value,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
