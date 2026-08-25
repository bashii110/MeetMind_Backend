<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    private function apiKey(): string
    {
        $key = (string) config('services.gemini.api_key');

        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        return $key;
    }

    private function model(string $type): string
    {
        return (string) config(
            "services.gemini.{$type}_model",
            'gemini-2.5-flash'
        );
    }

    private function generateContent(array $contents, string $model): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::withHeaders([
            'x-goog-api-key' => $this->apiKey(),
            'Content-Type' => 'application/json',
        ])
        ->timeout(180)
        ->post($url, [
            'contents' => $contents,
        ])
        ->throw();

        return $response->json();
    }

    /**
     * Transcribe an audio file using Gemini.
     *
     * @return array{text: string, language: ?string}
     */
    public function transcribe(string $absoluteFilePath): array
    {
        if (! is_readable($absoluteFilePath)) {
            throw new RuntimeException(
                "Audio file not readable: {$absoluteFilePath}"
            );
        }

        $audioBytes = file_get_contents($absoluteFilePath);

        if ($audioBytes === false) {
            throw new RuntimeException(
                "Unable to read audio file: {$absoluteFilePath}"
            );
        }

        $mimeType = mime_content_type($absoluteFilePath);

        if (! $mimeType) {
            $extension = strtolower(
                pathinfo($absoluteFilePath, PATHINFO_EXTENSION)
            );

            $mimeType = match ($extension) {
                'm4a' => 'audio/mp4',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'flac' => 'audio/flac',
                'aac' => 'audio/aac',
                default => 'application/octet-stream',
            };
        }

        $base64Audio = base64_encode($audioBytes);

        $response = $this->generateContent(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' =>
                                'Transcribe this audio recording exactly. ' .
                                'Return only the spoken transcript. ' .
                                'Do not summarize it. ' .
                                'Preserve the original language of the speech.',
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $base64Audio,
                            ],
                        ],
                    ],
                ],
            ],
            $this->model('transcribe')
        );

        $text = $this->extractResponseText($response);

        return [
            'text' => $text,
            'language' => null,
        ];
    }

    /**
     * Generate meeting summary.
     *
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
  "bullet_summary": ["short bullet point"],
  "decisions": ["decision made during the meeting"],
  "risks": ["risk or concern raised"],
  "next_steps": ["action or follow-up mentioned"],
  "deadlines": ["any date/deadline mentioned, as plain text"],
  "mood": "positive | neutral | tense"
}
JSON;

        $prompt = <<<TEXT
You are an assistant that summarizes meeting transcripts.

Respond with ONLY a valid JSON object matching this exact structure:

{$schema}

Do not add Markdown.
Do not add ```json.
Do not add explanations.

Meeting transcript:

{$transcriptText}
TEXT;

        $response = $this->generateContent(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            $this->model('chat')
        );

        $content = $this->extractResponseText($response);

        $content = $this->cleanJson($content);

        $result = json_decode($content, true);

        if (! is_array($result)) {
            throw new RuntimeException(
                'Gemini returned invalid JSON for meeting summary.'
            );
        }

        return [
            'executive_summary' =>
                (string) ($result['executive_summary'] ?? ''),

            'bullet_summary' =>
                $this->stringArray($result['bullet_summary'] ?? []),

            'decisions' =>
                $this->stringArray($result['decisions'] ?? []),

            'risks' =>
                $this->stringArray($result['risks'] ?? []),

            'next_steps' =>
                $this->stringArray($result['next_steps'] ?? []),

            'deadlines' =>
                $this->stringArray($result['deadlines'] ?? []),

            'mood' =>
                in_array(
                    $result['mood'] ?? null,
                    ['positive', 'neutral', 'tense'],
                    true
                )
                    ? $result['mood']
                    : 'neutral',
        ];
    }

    /**
     * Extract actionable tasks from transcript.
     *
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
      "suggested_assignee_name": "person's name if mentioned, else null",
      "suggested_deadline": "YYYY-MM-DD if mentioned or inferable, else null",
      "suggested_priority": "low | medium | high"
    }
  ]
}
JSON;

        $prompt = <<<TEXT
You extract actionable tasks/action-items from a meeting transcript.

Respond with ONLY a valid JSON object matching this exact structure:

{$schema}

If there are no clear action items, return:

{"tasks":[]}

Never invent tasks that were not discussed.

Do not add Markdown.
Do not add ```json.
Do not add explanations.

Meeting transcript:

{$transcriptText}
TEXT;

        $response = $this->generateContent(
            [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            $this->model('chat')
        );

        $content = $this->extractResponseText($response);

        $content = $this->cleanJson($content);

        $result = json_decode($content, true);

        if (! is_array($result)) {
            throw new RuntimeException(
                'Gemini returned invalid JSON for task extraction.'
            );
        }

        $tasks = $result['tasks'] ?? [];

        if (! is_array($tasks)) {
            return [];
        }

        return array_values(
            array_map(
                function ($task) {
                    return [
                        'title' =>
                            (string) ($task['title'] ?? ''),

                        'description' =>
                            $task['description'] ?? null,

                        'suggested_assignee_name' =>
                            $task['suggested_assignee_name'] ?? null,

                        'suggested_deadline' =>
                            $task['suggested_deadline'] ?? null,

                        'suggested_priority' =>
                            in_array(
                                $task['suggested_priority'] ?? null,
                                ['low', 'medium', 'high'],
                                true
                            )
                                ? $task['suggested_priority']
                                : 'medium',
                    ];
                },
                array_filter(
                    $tasks,
                    fn ($task) =>
                        is_array($task)
                        && ! empty($task['title'])
                )
            )
        );
    }

    private function extractResponseText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];

        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        if ($text === '') {
            throw new RuntimeException(
                'Gemini returned an empty response.'
            );
        }

        return trim($text);
    }

    private function cleanJson(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
        } elseif (str_starts_with($content, '```')) {
            $content = substr($content, 3);
        }

        if (str_ends_with($content, '```')) {
            $content = substr($content, 0, -3);
        }

        return trim($content);
    }

    private function stringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_map(
                'strval',
                array_filter(
                    $value,
                    fn ($v) => is_scalar($v)
                )
            )
        );
    }
}