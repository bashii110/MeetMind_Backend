# Phase 8 Backend — AI Chat Assistant & Search

Delivers PHASES.md Phase 8's backend: a contextualized AI assistant
query endpoint per meeting, and a global search endpoint across
meetings, transcripts, tasks, users, tags, and summaries.

**Nothing here touches `app/Jobs/` or anything else you've already
changed and pushed.** Three files you own already needed one small
addition each — for those, this delivers an exact snippet to paste in
by hand, not a full-file replacement, so your existing edits are safe.
Everything else is a brand-new file.

## New files (drop in as-is)
```
app/Services/AssistantService.php
app/Services/SearchService.php
app/Http/Requests/Assistant/AssistantQueryRequest.php
app/Http/Requests/Search/SearchRequest.php
app/Http/Controllers/Api/V1/AssistantController.php
app/Http/Controllers/Api/V1/SearchController.php
tests/Feature/Assistant/AssistantTest.php
tests/Feature/Search/SearchTest.php
```

## Files you already modified — hand-merge these snippets

### 1. `app/Services/AiService.php`
Add this public method anywhere alongside `generateSummary()`/
`extractTasks()` (it calls the same private `generateContent()`/
`model()`/`extractResponseText()` helpers already in that file, so no
other changes are needed there):

```php
    /**
     * FR-11.1/11.2 (Phase 8): answers a free-text question about a
     * meeting, given a pre-built text context (transcript excerpt,
     * summary, task list — see AssistantService::buildContext).
     * Deliberately not structured-JSON output like generateSummary()/
     * extractTasks() above: an assistant reply is meant to be read
     * directly (a paragraph, an email draft, a bullet list of minutes),
     * so plain text is the right shape here rather than something to
     * re-parse. One prompt covers every example in PHASES.md Phase 8
     * ("summarize this meeting," "who owns Task X," pending tasks, next
     * deadline, follow-up email, meeting minutes, project plan) — the
     * model branches on the free-text query itself rather than the
     * caller hand-routing intents.
     */
    public function assistantQuery(string $context, string $query): string
    {
        $prompt = <<<TEXT
You are the AI assistant embedded in MeetMind AI, a meeting-notes and
task-management app. Answer the user's question using ONLY the meeting
context below. If the context doesn't contain the answer, say so plainly
instead of guessing.

The user may ask you to: summarize the meeting, identify who owns a
task, list pending tasks, find the next deadline, draft a follow-up
email, write meeting minutes, or turn the discussion into a project
plan. Match your response format to what they asked for (e.g. an email
draft should look like an email; meeting minutes should be structured
with headings/bullets; a project plan should list phases or steps).

Meeting context:
{$context}

User question: {$query}
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

        return $this->extractResponseText($response);
    }
```

### 2. `app/Providers/AppServiceProvider.php`
Add a dedicated rate limiter for AI-triggering endpoints, next to the
existing `api`/`auth` limiters in `boot()` (ARCHITECTURE.md §6: "Rate
limiting middleware... especially on auth and AI endpoints" — the
assistant is the first endpoint that calls Gemini synchronously inside
a request rather than from a queued job, so it's the first one that
needs its own limiter rather than relying on the general `api` one):

```php
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });
```

(`RateLimiter`, `Limit`, and `Request` are already imported in that
file for the existing limiters.)

### 3. `routes/api.php`
Add these two imports near the other `App\Http\Controllers\Api\V1\*`
imports:

```php
use App\Http\Controllers\Api\V1\AssistantController;
use App\Http\Controllers\Api\V1\SearchController;
```

Add these two routes inside the existing `Route::middleware('auth:sanctum')->group(...)` block (anywhere is fine — e.g. right after the `task-candidates` routes, before the `tasks` resource block):

```php
        // Phase 8 — AI Chat Assistant & Search.
        Route::post('/meetings/{meeting}/assistant/query', [AssistantController::class, 'query'])
            ->middleware('throttle:ai')
            ->name('api.v1.meetings.assistant.query');

        Route::get('/search', [SearchController::class, 'index'])->name('api.v1.search.index');
```

## Design notes

**One endpoint, not seven.** SRD FR-11.1/FR-11.2 lists several assistant
capabilities (summarize, "who owns Task X," pending tasks, next
deadline, follow-up email, meeting minutes, project plan). Rather than
a route per capability, `POST /meetings/{meeting}/assistant/query`
takes one free-text `query` string; `AssistantService` builds a single
rich text context (organizer, participants, AI summary sections,
transcript excerpt, and the full task list with status/priority/
assignee/deadline) and `AiService::assistantQuery()` lets the model
itself decide how to shape the answer based on what was asked. This
mirrors how `AiService` already treats `generateSummary()` and
`extractTasks()` as fixed-shape calls, but an assistant reply has no
fixed shape (a paragraph vs. an email vs. a bulleted plan), so JSON
schema-constraining it the way those two methods do would fight the
feature rather than help it.

**Context, not RAG.** For a single meeting's transcript (capped at
~8k characters via `Str::limit`) plus its summary and tasks, the whole
thing comfortably fits in one prompt — there's no chunking/embedding/
vector-search pipeline here, deliberately, since PHASES.md scopes
Phase 8's assistant to "in a meeting," not cross-meeting semantic
search (that's what the global search endpoint below is for, and it's
sufficient for FR-12.1 as written).

**Search is LIKE-based, not FULLTEXT.** Every existing search in this
codebase (`MeetingRepository::forUser`, `TaskRepository::forUser`)
already uses plain `LIKE '%term%'` queries rather than
database-specific FULLTEXT indexes, specifically because the test
suite runs against SQLite (`phpunit.xml`) while production runs MySQL,
and MySQL's `MATCH...AGAINST` syntax has no SQLite equivalent.
`SearchService` follows the same convention for consistency and so
the new `tests/Feature/Search/SearchTest.php` needs no special DB
setup.

**Search results are grouped by type, not interleaved.** The response
shape is `{ meetings, tasks, transcripts, summaries, users }` rather
than one flat ranked list — matching DESIGN.md's implied search UI
(a person searching for something in MeetMind AI is usually looking
for "a meeting," "a task," or "a person," not a Google-style ranked
blend) and keeping the controller trivial (reusing `MeetingResource`/
`TaskResource`/`UserResource` as-is, no new response DTOs).
`transcripts`/`summaries` are returned as the *meetings* whose
transcript/summary matched — the search is "find the meeting," not
"find the paragraph," so the caller gets something navigable
(`MeetingResource`) rather than a raw transcript blob to display
out of context.

**No new migrations.** Both features query existing tables
(`meetings`, `tasks`, `transcripts`, `summaries`, `users`,
`workspace_members`) with `LIKE`/`whereHas`, so nothing new needed
indexing or schema changes for this phase's scope.

## Deferred

- **Frontend** (in-meeting assistant chat panel with suggested
  prompts, global search UI) is intentionally not part of this
  delivery — per the project's standing convention, the entire backend
  is finished across all phases before any Flutter work begins.
- **Cross-meeting / semantic search** (e.g. "what did we say about
  pricing across all our meetings") is out of scope for Phase 8 as
  specified; the assistant is scoped to one meeting's context, and
  global search is keyword-based, matching FR-12.1's literal wording
  ("search across meeting titles, transcripts, tasks, users, dates,
  tags, and AI summaries") rather than a broader RAG-style feature.
- **Rate-limit tuning** — 15/min on the `ai` limiter is a starting
  point; adjust once real Gemini quota/cost data is available.

## Endpoints added
```
POST /api/v1/meetings/{meeting}/assistant/query   (auth:sanctum, throttle:ai — { query: string })
GET  /api/v1/search?q=...                          (auth:sanctum — { meetings, tasks, transcripts, summaries, users })
```
