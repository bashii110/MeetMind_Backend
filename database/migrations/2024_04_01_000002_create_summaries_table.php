<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transcript_id')->constrained()->cascadeOnDelete();

            $table->text('executive_summary');
            $table->json('bullet_summary'); // string[]
            $table->json('decisions'); // string[]
            $table->json('risks'); // string[]
            $table->json('next_steps'); // string[]
            $table->json('deadlines'); // string[]
            $table->string('mood')->nullable(); // positive | neutral | tense — App\Enums\MeetingMood

            $table->timestamps();

            $table->unique('meeting_id'); // regenerating replaces, doesn't duplicate — see SummaryGenerationService
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
