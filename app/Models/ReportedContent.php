<?php

namespace App\Models;

use App\Enums\ContentReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FR-16.3 / DESIGN.md 3.13 "Reported content queue". Deliberately generic
 * over `subject` (Task, Meeting, TaskComment, ...) via a morph relation,
 * same pattern as ActivityLog — one table serves every reportable content
 * type instead of a table per type.
 */
class ReportedContent extends Model
{
    protected $table = 'reported_content';

    protected $fillable = [
        'workspace_id',
        'reported_by',
        'subject_type',
        'subject_id',
        'reason',
        'details',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
