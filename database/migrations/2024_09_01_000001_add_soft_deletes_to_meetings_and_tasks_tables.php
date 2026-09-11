<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hard delete gives a reconnecting offline client no way to learn
     * that a meeting/task it has cached locally is gone — the row is
     * simply absent from any future list response, indistinguishable
     * from "never fetched it". Soft-deleting gives SyncService a
     * `deleted_at` to report as an explicit tombstone (FR-15.2), while
     * every existing query keeps working unchanged since Eloquent's
     * SoftDeletes trait excludes trashed rows by default.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
