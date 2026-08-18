<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_candidates', function (Blueprint $table) {
            $table->foreignId('confirmed_task_id')->nullable()->after('status')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_task_id');
        });
    }
};
