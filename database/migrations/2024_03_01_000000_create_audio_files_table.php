<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('status')->default('pending'); // pending | uploading | uploaded | failed — App\Enums\AudioFileStatus
            $table->string('mime_type')->nullable();
            $table->string('extension', 10)->default('m4a');
            $table->unsignedBigInteger('total_size')->nullable(); // bytes, as declared at init
            $table->unsignedInteger('total_chunks')->nullable();
            $table->json('received_chunks')->nullable(); // int[] — lets a resumed upload skip what the server already has
            $table->unsignedInteger('duration_seconds')->nullable(); // client-reported
            $table->string('path')->nullable(); // final relative path on the 'local' disk once complete

            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_files');
    }
};
