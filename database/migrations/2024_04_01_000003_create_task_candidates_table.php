<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('suggested_assignee_name')->nullable(); // free text from the AI, e.g. a name mentioned in the transcript
            $table->foreignId('suggested_assignee_user_id')->nullable()->constrained('users')->nullOnDelete(); // set if that name matched a workspace member
            $table->date('suggested_deadline')->nullable();
            $table->string('suggested_priority')->default('medium');

            $table->string('status')->default('pending'); // pending | confirmed | dismissed — App\Enums\TaskCandidateStatus

            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_candidates');
    }
};
