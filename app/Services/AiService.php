<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ARCHITECTURE.md 3.4: transcription + summary + task-extraction, each a
 * plain HTTPS call to OpenAI. Throws on failure (via ->throw()) so the
 * calling Job's retry/backoff handles transient errors — see
 * App\Jobs\{TranscribeAudioJob,GenerateSummaryJob,ExtractTasksJob}.
 */
class AiService
{
    private function baseHeaders(): array
    {
        $org = config('services.openai.organization');

        return array_filter([
            'OpenAI-Organization' => $org ?: null,
        ]);
    }

    /**
     * @return array{text: string, language: ?string}
     */
    public function transcribe(string $absoluteFilePath): array
    {
        if (! is_readable($absoluteFilePath)) {
            throw new RuntimeException("Audio file not readable: {$absoluteFilePath}");
        }

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->withHeaders($this->baseHeaders())
            ->attach('file', file_get_contents($absoluteFilePath), basename($absoluteFilePath))
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => config('services.openai.transcribe_model', 'whisper-1'),
                'response_format' => 'verbose_json',
            ])
            ->throw();

        $data = $response->json();

        return [
            'text' => (string) ($data['text'] ?? ''),
            'language' => $data['language'] ?? null,
        ];
    }

    /**
     * @return array{
     *   executive_summary: string,
     *   bullet_summary: array<string>,
     *   decisions: array<string>,
     *   risks: array<string>,
     *   next_steps: array<string>,
     *   deadlines: array<string>,
     *   mood: string,
     * }
     */
    public function generateSummary(string $transcriptText): array
    {
        $schema = <<<'JSON'
            {
              "executive_summary": "2-4 sentence high-level summary",
              "bullet_summary": ["short bullet point", "..."],
              "decisions": ["decision made during the meeting", "..."],
              "risks": ["risk or concern raised", "..."],
              "next_steps": ["action or follow-up mentioned", "..."],
              "deadlines": ["any date/deadline mentioned, as plain text", "..."],
              "mood": "positive | neutral | tense"
            }
            JSON;

        $result = $this->chatJson(
            system: "You are an assistant that summarizes meeting transcripts. Respond with ONLY a JSON object matching this exact shape (all fields required, arrays may be empty):\n{$schema}",
            user: $transcriptText,
        );

        return [
            'executive_summary' => (string) ($result['executive_summary'] ?? ''),
            'bullet_summary' => $this->stringArray($result['bullet_summary'] ?? []),
            'decisions' => $this->stringArray($result['decisions'] ?? []),
            'risks' => $this->stringArray($result['risks'] ?? []),
            'next_steps' => $this->stringArray($result['next_steps'] ?? []),
            'deadlines' => $this->stringArray($result['deadlines'] ?? []),
            'mood' => in_array($result['mood'] ?? null, ['positive', 'neutral', 'tense'], true)
                ? $result['mood']
                : 'neutral',
        ];
    }

    /**
     * @return array<int, array{
     *   title: string,
     *   description: ?string,
     *   suggested_assignee_name: ?string,
     *   suggested_deadline: ?string,
     *   suggested_priority: string,
     * }>
     */
    public function extractTasks(string $transcriptText): array
    {
        $schema = <<<'JSON'
            {
              "tasks": [
                {
                  "title": "short, actionable task title",
                  "description": "one sentence of extra context, or null",
                  "suggested_assignee_name": "the person's name if mentioned, else null",
                  "suggested_deadline": "YYYY-MM-DD if a specific/inferable date was mentioned, else null",
                  "suggested_priority": "low | medium | high"
                }
              ]
            }
            JSON;

        $result = $this->chatJson(
            system: "You extract actionable tasks/action-items from a meeting transcript (FR-6.1/6.2). Respond with ONLY a JSON object matching this exact shape. If there are no clear action items, return an empty tasks array — never invent tasks that weren't discussed.\n{$schema}",
            user: $transcriptText,
        );

        $tasks = $result['tasks'] ?? [];
        if (! is_array($tasks)) {
            return [];
        }

        return array_values(array_map(function ($task) {
            return [
                'title' => (string) ($task['title'] ?? ''),
                'description' => $task['description'] ?? null,
                'suggested_assignee_name' => $task['suggested_assignee_name'] ?? null,
                'suggested_deadline' => $task['suggested_deadline'] ?? null,
                'suggested_priority' => in_array($task['suggested_priority'] ?? null, ['low', 'medium', 'high'], true)
                    ? $task['suggested_priority']
                    : 'medium',
            ];
        }, array_filter($tasks, fn ($t) => is_array($t) && ! empty($t['title']))));
    }

    private function chatJson(string $system, string $user): array
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->withHeaders($this->baseHeaders())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.chat_model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned a response that was not valid JSON.');
        }

        return $decoded;
    }

    private function stringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', array_filter($value, fn ($v) => is_scalar($v))));
    }
}
