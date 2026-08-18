<?php

namespace App\Enums;

/**
 * Tracks the full async pipeline described in ARCHITECTURE.md 3.4:
 * uploaded -> transcribing -> transcribed -> summarizing -> summarized.
 *
 * ARCHITECTURE.md 2.4's pending_upload -> uploaded -> processing ->
 * summarized flow is the *local* Flutter-side status, which is a coarser
 * view derived from this one (see frontend AudioUploadStatus) — this enum
 * is the server-side source of truth.
 */
enum AudioFileStatus: string
{
    case Pending = 'pending';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Transcribing = 'transcribing';
    case Transcribed = 'transcribed';
    case Summarizing = 'summarizing';
    case Summarized = 'summarized';
    case Failed = 'failed';
}
