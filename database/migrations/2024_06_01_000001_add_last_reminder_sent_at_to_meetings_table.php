<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // Mirrors tasks.last_reminder_sent_at (Phase 5) — see
            // TaskRepository::dueForReminder / SendTaskDeadlineReminders.
            // Same purpose here: stop meetings:send-reminders from
            // re-notifying the same upcoming meeting on every scheduler tick.
            $table->timestamp('last_reminder_sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('last_reminder_sent_at');
        });
    }
};
