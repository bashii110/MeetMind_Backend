<?php

namespace App\Services;

use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\Summary;
use App\Models\TaskCandidate;
use App\Models\Transcript;
use App\Repositories\Contracts\SummaryRepositoryInterface;
use App\Repositories\Contracts\TaskCandidateRepositoryInterface;
use App\Repositories\Contracts\TranscriptRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MeetingSummaryService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly TranscriptRepositoryInterface $transcripts,
        private readonly SummaryRepositoryInterface $summaries,
        private readonly TaskCandidateRepositoryInterface $taskCandidates,
    ) {}

    public function transcribeAudio(AudioFile $audioFile): Transcript
    {
        if (! $audioFile->path) {
            throw new RuntimeException("AudioFile {$audioFile->id} has no stored path yet.");
        }

        $absolutePath = Storage::disk('local')->path($audioFile->path);
        $result = $this->ai->transcribe($absolutePath);

        return $this->transcripts->create([
            'meeting_id' => $audioFile->meeting_id,
            'audio_file_id' => $audioFile->id,
            'text' => $result['text'],
            'language' => $result['language'],
        ]);
    }

    public function generateSummary(Meeting $meeting, Transcript $transcript): Summary
    {
        $result = $this->ai->generateSummary($transcript->text);

        // A meeting might be re-transcribed/re-summarized (e.g. a longer
        // follow-up recording); replace rather than accumulate duplicates,
        // per the unique(meeting_id) constraint on `summaries`.
        $existing = $meeting->summary;
        if ($existing) {
            return $this->summaries->update($existing, [
                'transcript_id' => $transcript->id,
                ...$result,
            ]);
        }

        return $this->summaries->create([
            'meeting_id' => $meeting->id,
            'transcript_id' => $transcript->id,
            ...$result,
        ]);
    }

    /**
     * @return Collection<int, TaskCandidate>
     */
    public function extractTasks(Meeting $meeting, Transcript $transcript): Collection
    {
        $results = $this->ai->extractTasks($transcript->text);
        $workspaceMemberIds = $meeting->workspace->members()->pluck('users.id', 'name');

        return collect($results)->map(function (array $task) use ($meeting, $transcript, $workspaceMemberIds) {
            $matchedUserId = null;
            if (! empty($task['suggested_assignee_name'])) {
                $matchedUserId = $this->matchAssigneeName($task['suggested_assignee_name'], $workspaceMemberIds);
            }

            return $this->taskCandidates->create([
                'meeting_id' => $meeting->id,
                'transcript_id' => $transcript->id,
                'title' => $task['title'],
                'description' => $task['description'],
                'suggested_assignee_name' => $task['suggested_assignee_name'],
                'suggested_assignee_user_id' => $matchedUserId,
                'suggested_deadline' => $this->parseDeadline($task['suggested_deadline']),
                'suggested_priority' => $task['suggested_priority'],
            ]);
        });
    }

    /**
     * Fuzzy-ish first-name match against workspace members — good enough
     * to pre-fill an assignee suggestion; the human confirming the
     * candidate (FR-6.3) can always correct it.
     */
    private function matchAssigneeName(string $suggestedName, Collection $membersByName): ?int
    {
        $needle = Str::lower(trim($suggestedName));

        foreach ($membersByName as $name => $userId) {
            if (Str::lower($name) === $needle || Str::contains(Str::lower($name), $needle)) {
                return $userId;
            }
        }

        return null;
    }

    private function parseDeadline(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (Throwable) {
            return null; // AI didn't give us a parseable date — leave it null rather than fail the whole job
        }
    }
}
