<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreatePortalSuperAdmin extends Command
{
    protected $signature = 'portal:create-superadmin {--username=} {--first-name=} {--last-name=} {--phone=}';
    protected $description = 'Create the initial SUPERADMIN account in shaafi_portal.users';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Username');
        $firstName = $this->option('first-name') ?: $this->ask('First name');
        $lastName = $this->option('last-name') ?: $this->ask('Last name');
        $phone = $this->option('phone') ?: $this->ask('Phone number (optional)');
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        if (! $username || ! $firstName || ! $lastName || ! $password || $password !== $confirmation) {
            $this->error('Username, first name, last name, and matching passwords are required.');
            return self::FAILURE;
        }
        if (User::where('username', $username)->exists()) {
            $this->error('That username already exists.');
            return self::FAILURE;
        }

        User::create([
            'firstName' => $firstName,
            'last_name' => $lastName,
            'number' => $phone ?: null,
            'username' => $username,
            'password' => Hash::make($password),
            'status' => 'ACTIVE',
            'role' => 'SUPERADMIN',
        ]);

        $this->info('SUPERADMIN account created in shaafi_portal.users.');
        return self::SUCCESS;
    }
}
