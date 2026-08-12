<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'cattie:admin {email?}';

    protected $description = 'Create or promote a Cattie administrator';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email address');
        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = User::create(['name' => $this->ask('Name'), 'email' => $email, 'password' => $this->secret('Password')]);
        }
        $user->update(['is_admin' => true]);
        $this->info("{$email} can now access /admin.");

        return self::SUCCESS;
    }
}
