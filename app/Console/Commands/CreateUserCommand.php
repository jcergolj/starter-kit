<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Features\Invitations\Mail\InvitationMail;
use App\Features\TenantDatabase\Exceptions\InvalidSubdomainFormat;
use App\Features\TenantDatabase\Exceptions\TemplateDatabaseNotFound;
use App\Features\TenantDatabase\Exceptions\TenantDatabaseAlreadyExists;
use App\Features\TenantDatabase\Exceptions\TenantDatabaseProvisioningFailed;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantDatabaseService;
use App\ValueObjects\EmailAddress;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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
        $tenantSubdomain = null;

        $subdomains = $tenantDatabaseService->getTenantSubdomains();

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

            $tenantSubdomain = $newTenantSubdomain;

            try {
                $tenantDatabaseService->createTenantDatabase($newTenantSubdomain);
            } catch (InvalidSubdomainFormat|TemplateDatabaseNotFound|TenantDatabaseAlreadyExists|TenantDatabaseProvisioningFailed $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $tenantDatabaseService->connectToTenant($newTenantSubdomain);
        }

        if ($where !== __('Current database') && $where !== __('New tenant database')) {
            $tenantSubdomain = $where;
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
            return $this->sendInvitation($role, $tenantSubdomain);
        }

        return $this->createDirectly($role, $newTenantSubdomain);
    }

    private function sendInvitation(RoleEnum $role, ?string $tenantSubdomain = null): int
    {
        $email = text(
            label: __('Email'),
            required: true,
            validate: [
                'email' => [
                    'required',
                    'email',
                    Rule::unique(User::class, 'email'),
                    Rule::unique(Invitation::class)->where(function (Builder $query): void {
                        $query->whereNull('accepted_at')
                            ->where('expires_at', '>', now());
                    }),
                ],
            ],
        );

        $email = EmailAddress::normalize($email);

        $languages = array_map(
            function (string $path) {
                return basename($path, '.json');
            },
            glob(lang_path('*.json')),
        );

        $lang = select(
            label: __('Language'),
            options: $languages,
        );

        $invitation = Invitation::createFor($email, $role, $lang);

        App::setLocale($lang);

        Mail::to($invitation->email)->send(new InvitationMail($invitation, $tenantSubdomain));

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

        $email = EmailAddress::normalize($email);

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
