<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = system-generated
            $table->string('action'); // e.g. 'meeting.created' — see App\Enums\ActivityAction
            $table->nullableMorphs('subject'); // subject_type / subject_id — the Meeting/Task/etc this entry is about
            $table->json('metadata')->nullable(); // small denormalized snapshot (titles, etc) so the timeline
                                                    // doesn't need to join back to a possibly-deleted subject
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
