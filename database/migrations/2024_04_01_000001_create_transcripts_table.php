<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_file_id')->constrained()->cascadeOnDelete();
            $table->longText('text');
            $table->string('language')->nullable();
            $table->timestamps();

            $table->unique('audio_file_id'); // one transcript per audio file
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripts');
    }
};
