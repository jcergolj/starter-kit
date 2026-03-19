<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Exceptions\InvalidSubdomainFormat;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Create a user or send an invitation';

    public function handle(TenantDatabaseService $tenantDatabaseService): int
    {
        $newTenantSubdomain = null;

        $databases = glob(database_path('db/*.sqlite'));
        $subdomains = $databases ? array_map(fn (string $path) => basename($path, '.sqlite'), $databases) : [];

        $options = [__('Current database')];

        if (! config('app.single_db_per_app')) {
            $options[] = __('New tenant database');
            $options = array_merge($options, $subdomains);
        }

        $where = select(
            label: __('Where should the user be added?'),
            options: $options,
        );

        if ($where === __('New tenant database')) {
            $newTenantSubdomain = text(
                label: __('Subdomain'),
                required: true,
            );

            try {
                $tenantDatabaseService->createTenantDatabase($newTenantSubdomain);
            } catch (InvalidSubdomainFormat) {
                $this->error(__('Invalid subdomain format.'));

                return self::FAILURE;
            }

            $tenantDatabaseService->connectToTenant($newTenantSubdomain);
        }

        if ($where !== __('Current database') && $where !== __('New tenant database')) {
            $tenantDatabaseService->connectToTenant($where);
        }

        $roleChoice = select(
            label: __('User role?'),
            options: [
                RoleEnum::User->trans(),
                RoleEnum::Admin->trans(),
                RoleEnum::Superadmin->trans(),
            ],
        );

        $role = match ($roleChoice) {
            RoleEnum::Superadmin->trans() => RoleEnum::Superadmin,
            RoleEnum::Admin->trans() => RoleEnum::Admin,
            default => RoleEnum::User,
        };

        $how = select(
            label: __('How should the user be created?'),
            options: [
                __('Send invitation'),
                __('Create directly'),
            ],
        );

        if ($how === __('Send invitation')) {
            return $this->sendInvitation($role);
        }

        return $this->createDirectly($role, $newTenantSubdomain);
    }

    private function sendInvitation(RoleEnum $role): int
    {
        $email = text(
            label: __('Email'),
            required: true,
            validate: ['email' => 'required|email|unique:users,email'],
        );

        $languages = array_map(
            fn (string $path) => basename($path, '.json'),
            glob(lang_path('*.json')),
        );

        $lang = select(
            label: __('Language'),
            options: $languages,
        );

        $invitation = Invitation::createFor($email, $role, $lang);

        App::setLocale($lang);

        Mail::to($invitation->email)->send(new InvitationMail($invitation));

        $this->components->info(__('Invitation sent successfully.'));

        return self::SUCCESS;
    }

    private function createDirectly(RoleEnum $role, ?string $subdomain = null): int
    {
        $name = text(
            label: __('Name'),
            required: true,
            validate: ['name' => 'required|max:255'],
        );

        $username = $subdomain ?? text(
            label: __('Username'),
            required: true,
            validate: ['username' => 'required|max:20|unique:users,username'],
        );

        $email = text(
            label: __('Email'),
            required: true,
            validate: ['email' => 'required|email|unique:users,email'],
        );

        $password = password(
            label: __('Password'),
            required: true,
            validate: ['password' => 'required|min:8'],
        );

        User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        $this->components->info(__('User created successfully.'));

        return self::SUCCESS;
    }
}
