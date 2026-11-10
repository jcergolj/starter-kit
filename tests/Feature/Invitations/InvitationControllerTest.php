<?php

declare(strict_types=1);

namespace Tests\Feature\Invitations;

use App\Features\Invitations\Controllers\InvitationController;
use App\Features\Invitations\Mail\InvitationMail;
use App\Features\Invitations\Requests\SendInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(InvitationController::class)]
class InvitationControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    private ?string $databaseRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        InAppNotification::fake();
    }

    protected function tearDown(): void
    {
        if ($this->databaseRoot !== null) {
            DB::purge('tenant');

            DB::purge('application');

            foreach (glob($this->databaseRoot.'/*') ?: [] as $path) {
                unlink($path);
            }

            rmdir($this->databaseRoot);

            config(['database.default' => 'sqlite']);
        }

        parent::tearDown();
    }

    #[Test]
    public function create_has_auth_verified_and_admin_middleware(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('invitations.create'));

        $response->assertMiddlewareIsApplied('auth');

        $response->assertMiddlewareIsApplied('verified');

        $response->assertMiddlewareIsApplied('admin');
    }

    #[Test]
    public function store_has_form_request(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('invitations.store'));

        $this->assertContainsFormRequest(SendInvitationRequest::class);
    }

    #[Test]
    public function admin_can_view_invite_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('invitations.create'));

        $response->assertOk()
            ->assertViewIs('invitations::invitations.create')
            ->assertViewHasForm('id="create-invitation"', 'POST', route('invitations.store'))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function non_admin_gets_403(): void
    {
        $nonAdmin = User::factory()->create();

        $response = $this->actingAs($nonAdmin)->get(route('invitations.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('invitations.create'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_send_invite_email(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'invite@example.com',
        ]);

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertSuccess(__('Invitation sent successfully.'));

        $invitation = Invitation::first();

        $this->assertSame('invite@example.com', $invitation->email);

        Mail::assertSent(InvitationMail::class, function ($mail) {
            return $mail->hasTo('invite@example.com');
        });
    }

    #[Test]
    public function tenant_invitation_email_link_can_be_followed_and_accepted(): void
    {
        $this->databaseRoot = sys_get_temp_dir().'/invitation-tests-'.bin2hex(random_bytes(8));

        mkdir($this->databaseRoot, 0700);

        config([
            'app.domain' => 'example.com',
            'app.url' => 'https://example.com:8443',
            'app.single_db_per_app' => false,
            'database.connections.application' => array_replace(config('database.connections.sqlite'), [
                'database' => $this->databaseRoot.'/application.sqlite',
            ]),
            'database.connections.tenant.database' => $this->databaseRoot.'/acme.sqlite',
            'database.default' => 'application',
        ]);

        touch($this->databaseRoot.'/application.sqlite');

        touch($this->databaseRoot.'/acme.sqlite');

        $this->artisan('migrate', ['--database' => 'application', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('migrate', ['--database' => 'tenant', '--no-interaction' => true])->assertSuccessful();

        $tenantDatabase = new TenantDatabaseService($this->databaseRoot);

        $this->app->instance(TenantDatabaseService::class, $tenantDatabase);

        $tenantDatabase->connectToTenant('acme');

        $admin = User::factory()->admin()->create(['username' => 'acme']);

        $tenantDatabase->resetToApplicationDatabase();

        Mail::fake();

        $this->actingAs($admin)->post('https://acme.example.com:8443/invitations', [
            'email' => 'invite@example.com',
        ])->assertRedirect();

        $invitation = Invitation::on('tenant')->sole();

        $url = 'https://acme.example.com:8443/invite/'.$invitation->token;

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($url): bool {
            $mail->assertSeeInHtml('href="'.$url.'"', false);

            return true;
        });

        $this->app['auth']->forgetGuards();

        $this->flushSession();

        $this->get($url)->assertOk()->assertSee('invite@example.com');

        $this->post($url, [
            'name' => 'Invited User',
            'username' => 'invited-user',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertRedirect('https://acme.example.com:8443/login')
            ->assertSessionHas('status', __('Invitation accepted. You can now log in.'));

        $acceptedInvitation = Invitation::on('tenant')->findOrFail($invitation->id);

        $this->assertNotNull($acceptedInvitation->accepted_at);

        $user = User::on('tenant')->where('email', 'invite@example.com')->firstOrFail();

        $this->assertSame('Invited User', $user->name);

        $this->assertDatabaseEmpty('invitations', 'application');

        $this->assertDatabaseEmpty('users', 'application');
    }

    #[Test]
    #[TestWith([false, 'example.com'])]
    #[TestWith([true, 'example.com'])]
    #[TestWith([true, 'acme.example.com'])]
    public function application_database_invitations_keep_root_domain_links(bool $singleDatabase, string $host): void
    {
        config([
            'app.domain' => 'example.com',
            'app.url' => 'https://example.com:8443',
            'app.single_db_per_app' => $singleDatabase,
        ]);

        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('https://'.$host.':8443/invitations', [
            'email' => 'invite@example.com',
        ])->assertRedirect();

        $invitation = Invitation::sole();

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($invitation): bool {
            $mail->assertSeeInHtml('href="https://example.com:8443/invite/'.$invitation->token.'"', false);

            return true;
        });
    }

    #[Test]
    public function failed_invitation_delivery_removes_invitation_and_reports_error(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('failed@example.com')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'failed@example.com',
        ]);

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertError(__('Invitation could not be sent. Please try again.'));

        $this->assertDatabaseMissing('invitations', ['email' => 'failed@example.com']);
    }

    #[Test]
    public function invitation_can_be_retried_after_delivery_failure(): void
    {
        $deliveryAttempts = 0;

        Mail::shouldReceive('to')
            ->twice()
            ->with('retry@example.com')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->twice()
            ->andReturnUsing(function () use (&$deliveryAttempts): void {
                throw_if($deliveryAttempts++ === 0, RuntimeException::class, 'SMTP unavailable');
            });

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'retry@example.com',
        ]);

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'retry@example.com',
        ]);

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertSuccess(__('Invitation sent successfully.'));

        $this->assertDatabaseHas('invitations', ['email' => 'retry@example.com']);
    }

    #[Test]
    public function admin_invite_email_is_normalized(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => '  Invite@Example.COM ',
        ]);

        $this->assertSame('invite@example.com', Invitation::sole()->email);
    }

    #[Test]
    public function duplicate_pending_email_fails_validation(): void
    {
        $admin = User::factory()->admin()->create();

        Invitation::factory()->create(['email' => 'pending@example.com']);

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => 'pending@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function expired_invitation_is_reissued_with_a_new_token(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $invitation = Invitation::factory()->expired()->create(['email' => 'expired@example.com']);

        $oldToken = $invitation->token;

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => $invitation->email,
        ]);

        $response->assertRedirect(route('invitations.create'));

        $reissued = Invitation::sole();

        $this->assertNotSame($oldToken, $reissued->token);

        $this->assertTrue($reissued->isPending());

        $this->get(route('invitations.accept', $oldToken))->assertNotFound();

        Mail::assertSent(InvitationMail::class);
    }

    #[Test]
    public function accepted_invitation_for_deleted_user_is_reissued(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $invitation = Invitation::factory()->accepted()->create(['email' => 'deleted@example.com']);

        $response = $this->actingAs($admin)->post(route('invitations.store'), [
            'email' => $invitation->email,
        ]);

        $response->assertRedirect(route('invitations.create'));

        $this->assertTrue(Invitation::sole()->isPending());

        $this->assertSame(1, Invitation::count());
    }

    #[Test]
    public function pending_invitation_list_is_paginated_in_stable_order(): void
    {
        $admin = User::factory()->admin()->create();

        $invitations = Invitation::factory()->count(16)->create();

        $firstPage = $this->actingAs($admin)->get(route('invitations.create'));

        $secondPage = $this->actingAs($admin)->get(route('invitations.create', ['page' => 2]));

        $firstPage->assertOk()
            ->assertSeeText($invitations[0]->email)
            ->assertDontSeeText($invitations[15]->email)
            ->assertSee('page=2');

        $secondPage->assertOk()
            ->assertDontSeeText($invitations[0]->email)
            ->assertSeeText($invitations[15]->email);
    }

    #[Test]
    public function empty_pending_invitation_list_hides_the_list_and_pagination_links(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('invitations.create'));

        $response->assertOk()
            ->assertDontSeeText(__('Pending invitations'))
            ->assertDontSee('page=');
    }

    #[Test]
    public function admin_can_revoke_pending_invitation(): void
    {
        $admin = User::factory()->admin()->create();

        $invitation = Invitation::factory()->create();

        $response = $this->actingAs($admin)->delete(route('invitations.destroy', $invitation));

        $response->assertRedirect(route('invitations.create'));

        InAppNotification::assertSuccess(__('Invitation revoked.'));
    }

    #[Test]
    public function revoked_invitation_is_deleted_from_db(): void
    {
        $admin = User::factory()->admin()->create();

        $invitation = Invitation::factory()->create();

        $this->actingAs($admin)->delete(route('invitations.destroy', $invitation));

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }
}
