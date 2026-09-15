<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Controllers\AcceptInvitationController;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AcceptInvitationController::class)]
class AcceptInvitationControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function store_has_form_request(): void
    {
        $invitation = Invitation::factory()->create();
        $this->post(route('accept.invitations.store', $invitation->token));

        $this->assertContainsFormRequest(AcceptInvitationRequest::class);
    }

    #[Test]
    public function show_renders_accept_form_for_valid_token(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertOk()
            ->assertViewIs('invitations.accept')
            ->assertViewHasForm('id="accept-invitation-form"', 'POST', route('accept.invitations.store', $invitation->token))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasTextInput('name')
            ->assertFormHasTextInput('username')
            ->assertFormHasPasswordInput('password')
            ->assertFormHasPasswordInput('password_confirmation')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function show_returns_404_for_unknown_token(): void
    {
        $response = $this->get(route('invitations.accept', 'unknowntoken'));

        $response->assertNotFound();
    }

    #[Test]
    public function show_redirects_when_expired(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertRedirect(route('login'));

        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
    }

    #[Test]
    public function show_redirects_when_already_accepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $response = $this->get(route('invitations.accept', $invitation->token));

        $response->assertRedirect(route('login'));

        $response->assertSessionHas('status', __('This invitation is no longer valid.'));
    }

    #[Test]
    public function store_creates_user_with_correct_fields(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'invited@example.com']);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::first();

        $this->assertSame('Jane Doe', $user->name);

        $this->assertSame('janedoe', $user->username);

        $this->assertSame('invited@example.com', $user->email);
    }

    #[Test]
    public function store_saves_invitation_lang_in_user_settings(): void
    {
        $invitation = Invitation::factory()->create(['lang' => 'sl']);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();

        $this->assertSame('sl', $user->settings->lang);
    }

    #[Test]
    public function store_creates_admin_user_when_invitation_is_admin(): void
    {
        $invitation = Invitation::factory()->create(['role' => RoleEnum::Admin]);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Admin User',
            'username' => 'adminuser',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();

        $this->assertSame(RoleEnum::Admin, $user->role);
    }

    #[Test]
    public function store_creates_regular_user_when_invitation_is_not_admin(): void
    {
        $invitation = Invitation::factory()->create(['role' => RoleEnum::User]);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Regular User',
            'username' => 'regularuser',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();

        $this->assertSame(RoleEnum::User, $user->role);
    }

    #[Test]
    public function store_sets_email_verified_at(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', $invitation->email)->first();

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function store_marks_invitation_accepted(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function store_email_comes_from_db_not_request(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'real@example.com']);

        $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'email' => 'spoofed@example.com',
            'role' => RoleEnum::Admin->value,
        ]);

        $user = User::sole();

        $this->assertSame('real@example.com', $user->email);

        $this->assertSame(RoleEnum::User, $user->role);

        $this->assertDatabaseMissing('users', ['email' => 'spoofed@example.com']);
    }

    #[Test]
    public function store_does_not_create_user_for_unknown_token(): void
    {
        $response = $this->post(route('accept.invitations.store', 'unknown-token'), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'email' => 'spoofed@example.com',
            'role' => RoleEnum::Admin->value,
        ]);

        $response->assertNotFound();

        $this->assertDatabaseEmpty('users');

        $this->assertDatabaseEmpty('invitations');
    }

    #[Test]
    public function store_redirects_to_login_with_accepted_status(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));

        $response->assertSessionHas('status', __('Invitation accepted. You can now log in.'));
    }

    #[Test]
    public function store_fails_on_expired_token(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));

        $response->assertSessionHas('status', __('This invitation is no longer valid.'));

        $this->assertDatabaseEmpty('users');
    }

    #[Test]
    public function store_fails_when_already_accepted(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'));

        $response->assertSessionHas('status', __('This invitation is no longer valid.'));

        $this->assertDatabaseEmpty('users');
    }

    #[Test]
    public function store_fails_without_consuming_invitation_when_email_already_exists(): void
    {
        $invitation = Invitation::factory()->create(['email' => 'existing@example.com']);
        User::factory()->create(['email' => $invitation->email]);

        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHas('status', __('This invitation is no longer valid.'));

        $this->assertSame(1, User::where('email', $invitation->email)->count());

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function store_rolls_back_invitation_claim_when_user_creation_fails(): void
    {
        $invitation = Invitation::factory()->create();
        $dispatcher = User::getEventDispatcher();

        User::creating(function (): void {
            throw new \RuntimeException('User creation failed.');
        });

        try {
            $response = $this->post(route('accept.invitations.store', $invitation->token), [
                'name' => 'Jane Doe',
                'username' => 'janedoe',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ]);
        } finally {
            User::setEventDispatcher($dispatcher);
        }

        $response->assertServerError();

        $this->assertDatabaseEmpty('users');

        $this->assertNull($invitation->fresh()->accepted_at);
    }

    #[Test]
    public function store_replay_does_not_create_a_second_user(): void
    {
        $invitation = Invitation::factory()->create();
        $payload = [
            'name' => 'Jane Doe',
            'username' => 'janedoe',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ];

        $this->post(route('accept.invitations.store', $invitation->token), $payload);
        $response = $this->post(route('accept.invitations.store', $invitation->token), [
            ...$payload,
            'username' => 'janetwo',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHas('status', __('This invitation is no longer valid.'));

        $this->assertSame(1, User::count());

        $this->assertSame(1, Invitation::count());
    }

    #[Test]
    public function concurrent_acceptance_has_one_winner_and_one_loser(): void
    {
        $databasePath = tempnam(storage_path('framework/testing'), 'invitation-');
        $barrierPath = $databasePath.'.ready';
        $resultPaths = [$databasePath.'.one', $databasePath.'.two'];
        $originalDatabase = config('database.connections.sqlite.database');
        $originalDefaultConnection = config('database.default');

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $databasePath,
            ]);
            DB::purge('sqlite');
            Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);

            $invitation = Invitation::factory()->create([
                'email' => 'concurrent@example.com',
            ]);
            $payload = [
                'name' => 'Concurrent User',
                'username' => 'concurrent',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ];
            $children = [];

            foreach ($resultPaths as $resultPath) {
                $pid = pcntl_fork();

                if ($pid === -1) {
                    self::fail('Unable to fork the concurrent acceptance test worker.');
                }

                if ($pid === 0) {
                    DB::purge('sqlite');

                    while (! file_exists($barrierPath)) {
                        Sleep::usleep(1000);
                    }

                    try {
                        $request = Request::create(
                            route('accept.invitations.store', $invitation->token),
                            'POST',
                            $payload,
                        );
                        $response = app()->handle($request);

                        file_put_contents($resultPath, json_encode([
                            'status' => $response->getStatusCode(),
                            'location' => $response->headers->get('Location'),
                            'session_status' => method_exists($response, 'getSession')
                                ? $response->getSession()->get('status')
                                : null,
                        ], JSON_THROW_ON_ERROR));
                    } catch (\Throwable $exception) {
                        file_put_contents($resultPath, json_encode([
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ], JSON_THROW_ON_ERROR));
                    }

                    exit(0);
                }

                $children[] = $pid;
            }

            touch($barrierPath);

            foreach ($children as $child) {
                pcntl_waitpid($child, $status);
                $this->assertSame(0, pcntl_wexitstatus($status));
            }

            $results = array_map(
                function (string $path): array {
                    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                },
                $resultPaths,
            );
            $successful = array_filter($results, function (array $result): bool {
                return ($result['session_status'] ?? null) === __('Invitation accepted. You can now log in.');
            });
            $invalid = array_filter($results, function (array $result): bool {
                return ($result['session_status'] ?? null) === __('This invitation is no longer valid.');
            });

            $this->assertCount(1, $successful, json_encode($results, JSON_THROW_ON_ERROR));
            $this->assertCount(1, $invalid, json_encode($results, JSON_THROW_ON_ERROR));
            $this->assertDatabaseCount('users', 1);
            $this->assertNotNull($invitation->fresh()->accepted_at);
        } finally {
            config([
                'database.default' => $originalDefaultConnection,
                'database.connections.sqlite.database' => $originalDatabase,
            ]);
            DB::purge('sqlite');

            foreach ([$databasePath, $barrierPath, ...$resultPaths] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
