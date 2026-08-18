<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // nullable: OAuth-only accounts have none
            $table->rememberToken();

            // ARCHITECTURE.md 4: users(role, timezone, avatar, provider)
            $table->string('role')->default('regular_user'); // regular_user | workspace_admin | system_admin
            $table->string('timezone')->default('UTC');
            $table->string('avatar')->nullable();
            $table->string('provider')->nullable(); // null = email/password, else 'google'
            $table->string('provider_id')->nullable();

            // SRD FR-14.1: bio, company, position, skills
            $table->text('bio')->nullable();
            $table->string('company')->nullable();
            $table->string('position')->nullable();
            $table->json('skills')->nullable();

            $table->boolean('is_disabled')->default(false); // FR-16.1 admin disable-account

            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
