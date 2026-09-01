<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Str;

/**
 * FR-11.1/11.2 — the AI Chat Assistant embedded in a meeting. Builds a
 * compact text context from the meeting's transcript, summary, and
 * tasks, then hands that + the user's free-text question to
 * AiService::assistantQuery().
 *
 * Deliberately no separate "intent classification" step (e.g. detecting
 * "who owns Task X" vs "draft a follow-up email" as different code
 * paths) — a single well-structured context plus the raw query gives
 * the model everything it needs to answer any of PHASES.md Phase 8's
 * example prompts (summarize, ownership lookups, pending tasks, next
 * deadline, follow-up email, meeting minutes, project plan) without a
 * brittle hand-rolled router in front of it.
 */
class AssistantService
{
    public function __construct(private readonly AiService $ai) {}

    public function ask(Meeting $meeting, string $query): string
    {
        $context = $this->buildContext($meeting);

        return $this->ai->assistantQuery($context, $query);
    }

    private function buildContext(Meeting $meeting): string
    {
        $meeting->loadMissing([
            'owner',
            'participants.user',
            'tasks.assignee',
            'summary',
            'transcripts',
        ]);

        $lines = [];

        $lines[] = "Meeting title: {$meeting->title}";
        if ($meeting->description) {
            $lines[] = "Description: {$meeting->description}";
        }
        $lines[] = 'Date: '.($meeting->date?->toDateString() ?? 'unknown').($meeting->time ? " at {$meeting->time}" : '');
        $lines[] = 'Status: '.($meeting->status?->value ?? 'unknown');

        if ($meeting->owner) {
            $lines[] = "Organizer: {$meeting->owner->name}";
        }

        $participantNames = $meeting->participants
            ->map(fn ($p) => $p->user?->name)
            ->filter()
            ->implode(', ');
        if ($participantNames !== '') {
            $lines[] = "Participants: {$participantNames}";
        }

        if ($meeting->summary) {
            $summary = $meeting->summary;
            $lines[] = '--- AI Summary ---';
            $lines[] = "Executive summary: {$summary->executive_summary}";
            if (! empty($summary->decisions)) {
                $lines[] = 'Decisions: '.implode('; ', $summary->decisions);
            }
            if (! empty($summary->risks)) {
                $lines[] = 'Risks: '.implode('; ', $summary->risks);
            }
            if (! empty($summary->next_steps)) {
                $lines[] = 'Next steps: '.implode('; ', $summary->next_steps);
            }
            if (! empty($summary->deadlines)) {
                $lines[] = 'Mentioned deadlines: '.implode('; ', $summary->deadlines);
            }
            if ($summary->mood) {
                $lines[] = "Meeting mood: {$summary->mood->value}";
            }
        }

        $transcript = $meeting->transcripts->last();
        if ($transcript) {
            // Capped so a very long recording doesn't blow past the
            // model's context window — the summary above already
            // captures the high-level content either way.
            $lines[] = '--- Transcript (excerpt) ---';
            $lines[] = Str::limit($transcript->text, 8000, '… [transcript truncated]');
        }

        if ($meeting->tasks->isNotEmpty()) {
            $lines[] = '--- Tasks from this meeting ---';
            foreach ($meeting->tasks as $task) {
                $lines[] = sprintf(
                    '- "%s" | status: %s | priority: %s | assignee: %s | deadline: %s',
                    $task->title,
                    $task->status?->value ?? 'pending',
                    $task->priority?->value ?? 'medium',
                    $task->assignee?->name ?? 'unassigned',
                    $task->deadline?->toDateString() ?? 'none',
                );
            }
        } else {
            $lines[] = 'No tasks have been created from this meeting yet.';
        }

        return implode("\n", $lines);
    }
}
