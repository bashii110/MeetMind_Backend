<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown by MeetingService/TaskService::update() when the caller supplies
 * a `client_updated_at` that no longer matches the record's current
 * `updated_at` — i.e. someone else changed it since the client's local
 * cache last synced (ARCHITECTURE.md 2.3: "last-write-wins... with a
 * manual merge prompt for conflicting task edits"). Carries the current
 * server copy so the catching controller can hand it straight back to
 * the client for that merge prompt without an extra query.
 *
 * Caught locally in MeetingController/TaskController rather than mapped
 * globally in bootstrap/app.php's exception handler — it's only ever
 * meaningful in the one or two call sites that opt into conflict
 * detection by sending `client_updated_at` in the first place.
 */
class SyncConflictException extends RuntimeException
{
    public function __construct(public readonly Model $current)
    {
        parent::__construct('This record was modified since your last sync.');
    }
}
