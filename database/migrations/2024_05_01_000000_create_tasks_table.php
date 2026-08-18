<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete(); // nullable: manually-created tasks aren't tied to a meeting
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low | medium | high — App\Enums\TaskPriority
            $table->string('status')->default('pending'); // pending | in_progress | completed | cancelled — App\Enums\TaskStatus
            $table->dateTime('deadline')->nullable();
            $table->unsignedTinyInteger('progress')->default(0); // 0-100, FR-7.5

            // Not in ARCHITECTURE.md's schema list — implementation detail
            // so the deadline-reminder scheduler (below) doesn't re-notify
            // the same task every time it runs.
            $table->timestamp('last_reminder_sent_at')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
