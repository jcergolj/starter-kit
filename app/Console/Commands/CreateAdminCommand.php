<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an admin user in single user mode';

    public function handle(): int
    {
        if (! config('app.single_user_mode')) {
            $this->error('This command is only available in single user mode.');

            return self::FAILURE;
        }

        if (User::exists()) {
            $this->error('A user already exists.');

            return self::FAILURE;
        }

        $name = $this->ask('Name');
        $username = $this->ask('Username');
        $email = $this->ask('Email');
        $password = $this->ask('Password');

        User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->components->info('Admin user created successfully.');

        return self::SUCCESS;
    }
}
