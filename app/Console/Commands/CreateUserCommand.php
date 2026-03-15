<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\InvalidSubdomainFormat;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class CreateUserCommand extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Create a user or send an invitation';

    public function handle(TenantDatabaseService $tenantDatabaseService): int
    {
        $newTenantSubdomain = null;

        $where = $this->choice(__('Where should the user be added?'), [
            __('Current database'),
            __('New tenant database'),
            __('Existing tenant database'),
        ]);

        if ($where === __('New tenant database')) {
            $newTenantSubdomain = $this->ask(__('Subdomain'));

            try {
                $tenantDatabaseService->createTenantDatabase($newTenantSubdomain);
            } catch (InvalidSubdomainFormat) {
                $this->error(__('Invalid subdomain format.'));

                return self::FAILURE;
            }

            $tenantDatabaseService->connectToTenant($newTenantSubdomain);
        }

        if ($where === __('Existing tenant database')) {
            $databases = glob(database_path('db/*.sqlite'));

            if ($databases === [] || $databases === false) {
                $this->error(__('No existing tenant databases found.'));

                return self::FAILURE;
            }

            $subdomains = array_map(fn (string $path) => basename($path, '.sqlite'), $databases);

            $subdomain = $this->choice(__('Where should the user be added?'), $subdomains);

            $tenantDatabaseService->connectToTenant($subdomain);
        }

        $role = $this->choice(__('User role?'), [
            __('User'),
            __('Admin'),
        ]);

        $isAdmin = $role === __('Admin');

        $how = $this->choice(__('How should the user be created?'), [
            __('Send invitation'),
            __('Create directly'),
        ]);

        if ($how === __('Send invitation')) {
            return $this->sendInvitation($isAdmin);
        }

        return $this->createDirectly($isAdmin, $newTenantSubdomain);
    }

    private function sendInvitation(bool $isAdmin): int
    {
        $email = $this->ask(__('Email'));

        $invitation = Invitation::createFor($email, $isAdmin);

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        $this->components->info(__('Invitation sent successfully.'));

        return self::SUCCESS;
    }

    private function createDirectly(bool $isAdmin, ?string $subdomain = null): int
    {
        $name = $this->ask(__('Name'));
        $username = $subdomain ?? $this->ask(__('Username'));
        $email = $this->ask(__('Email'));
        $password = $this->secret(__('Password'));

        User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => $isAdmin,
            'email_verified_at' => now(),
        ]);

        $this->components->info(__('User created successfully.'));

        return self::SUCCESS;
    }
}
