<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeSystemAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:system-admin';

    /**
     * The console command description.
     */
    protected $description = 'Create a system administrator user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->ask('Admin name');
        $email = $this->ask('Admin email');
        $password = $this->secret('Admin password');

        if (User::where('email', $email)->exists()) {
            $this->error('A user with this email already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'system_admin',
        ]);

        $this->info('System admin created successfully!');
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Role: {$user->role->value}");

        return self::SUCCESS;
    }
}