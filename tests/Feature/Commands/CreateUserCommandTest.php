<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\RoleEnum;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_regular_user_directly_on_current_database(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'John Doe')
            ->expectsQuestion(__('Username'), 'johndoe')
            ->expectsQuestion(__('Email'), 'john@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'john@example.com')->first();
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('johndoe', $user->username);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(RoleEnum::User, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_creates_admin_directly_on_current_database(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('Admin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Admin User')
            ->expectsQuestion(__('Username'), 'admin')
            ->expectsQuestion(__('Email'), 'admin@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertSame('Admin User', $user->name);
        $this->assertSame('admin', $user->username);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(RoleEnum::Admin, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_creates_superadmin_directly_on_current_database(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('Superadmin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Super Admin')
            ->expectsQuestion(__('Username'), 'superadmin')
            ->expectsQuestion(__('Email'), 'superadmin@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'superadmin@example.com')->first();
        $this->assertSame('Super Admin', $user->name);
        $this->assertSame('superadmin', $user->username);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(RoleEnum::Superadmin, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_sends_invitation_for_regular_user_on_current_database(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'invite@example.com')
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'invite@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame(RoleEnum::User, $invitation->role);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->invitation->email === 'invite@example.com');
    }

    #[Test]
    public function it_sends_invitation_for_admin_on_current_database(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('Admin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'admin-invite@example.com')
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'admin-invite@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame(RoleEnum::Admin, $invitation->role);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->invitation->email === 'admin-invite@example.com');
    }

    #[Test]
    public function it_sends_invitation_for_superadmin_on_current_database(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Current database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('User role?'), __('Superadmin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'superadmin-invite@example.com')
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'superadmin-invite@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame(RoleEnum::Superadmin, $invitation->role);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->invitation->email === 'superadmin-invite@example.com');
    }

    #[Test]
    public function it_creates_user_on_new_tenant_database(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('New tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsQuestion(__('Subdomain'), 'test-tenant')
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Tenant User')
            ->expectsQuestion(__('Email'), 'tenant@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'tenant@example.com')->first();
        $this->assertSame('Tenant User', $user->name);
        $this->assertSame('test-tenant', $user->username);
        $this->assertSame(RoleEnum::User, $user->role);
    }

    #[Test]
    public function it_creates_admin_on_new_tenant_database(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('New tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsQuestion(__('Subdomain'), 'admin-tenant')
            ->expectsChoice(__('User role?'), __('Admin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Tenant Admin')
            ->expectsQuestion(__('Email'), 'tenant-admin@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'tenant-admin@example.com')->first();
        $this->assertSame('Tenant Admin', $user->name);
        $this->assertSame('admin-tenant', $user->username);
        $this->assertSame(RoleEnum::Admin, $user->role);
    }

    #[Test]
    public function it_sends_invitation_on_new_tenant_database(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('New tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsQuestion(__('Subdomain'), 'invite-tenant')
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'tenant-invite@example.com')
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'tenant-invite@example.com')->first();
        $this->assertNotNull($invitation);
        $this->assertSame(RoleEnum::User, $invitation->role);

        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->invitation->email === 'tenant-invite@example.com');
    }

    #[Test]
    public function it_creates_user_on_existing_tenant_database(): void
    {
        $dbPath = database_path('db');

        if (! is_dir($dbPath)) {
            mkdir($dbPath, 0755, true);
        }

        $existingFiles = glob(database_path('db/*.sqlite'));

        foreach ($existingFiles as $file) {
            @unlink($file);
        }

        touch(database_path('db/existing-tenant.sqlite'));

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Existing tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsChoice(__('Where should the user be added?'), 'existing-tenant', [
                'existing-tenant',
            ])
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Existing Tenant User')
            ->expectsQuestion(__('Username'), 'existinguser')
            ->expectsQuestion(__('Email'), 'existing@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'existing@example.com')->first();
        $this->assertSame('Existing Tenant User', $user->name);

        @unlink(database_path('db/existing-tenant.sqlite'));
    }

    #[Test]
    public function it_fails_when_subdomain_is_invalid_for_new_tenant(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('New tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->expectsQuestion(__('Subdomain'), 'INVALID SUBDOMAIN!')
            ->assertFailed();
    }

    #[Test]
    public function it_shows_error_when_no_existing_tenant_databases_found(): void
    {
        $files = glob(database_path('db/*.sqlite'));

        foreach ($files as $file) {
            @unlink($file);
        }

        $this->artisan('app:create-user')
            ->expectsChoice(__('Where should the user be added?'), __('Existing tenant database'), [
                __('Current database'),
                __('New tenant database'),
                __('Existing tenant database'),
            ])
            ->assertFailed();
    }
}
