<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `client_ref` is a UUID the Flutter app generates locally when it
     * creates a meeting/task while offline (PHASES.md Phase 10's outbox
     * pattern). Replaying the same queued "create" request after
     * reconnecting — e.g. because the app was killed before it saw the
     * first response — must not create a duplicate; see
     * MeetingService::create() / TaskService::create().
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->uuid('client_ref')->nullable()->after('id');
            $table->unique(['workspace_id', 'client_ref']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->uuid('client_ref')->nullable()->after('id');
            $table->unique(['workspace_id', 'client_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'client_ref']);
            $table->dropColumn('client_ref');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'client_ref']);
            $table->dropColumn('client_ref');
        });
    }
};
