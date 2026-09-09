<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reported_content', function (Blueprint $table) {
            $table->id();
            // Nullable: a reported TaskComment's "workspace" is derived
            // from its parent task at report time (see ModerationService)
            // and could theoretically be null if that lookup ever fails.
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('subject'); // subject_type / subject_id — Task, Meeting, TaskComment, ...

            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); // pending | reviewed | dismissed | actioned — App\Enums\ContentReportStatus

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reported_content');
    }
};
