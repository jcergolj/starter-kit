<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(EnsureUserIsAdmin::class)]
final class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_passes_through(): void
    {
        $admin = User::factory()->admin()->create();
        $request = Request::create('/invitations/create');
        $request->setUserResolver(fn () => $admin);

        $nextCalled = false;
        $middleware = new EnsureUserIsAdmin;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function superadmin_passes_through(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $request = Request::create('/invitations/create');
        $request->setUserResolver(fn () => $superadmin);

        $nextCalled = false;
        $middleware = new EnsureUserIsAdmin;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function non_admin_gets_403(): void
    {
        $nonAdmin = User::factory()->create();
        $request = Request::create('/invitations/create');
        $request->setUserResolver(fn () => $nonAdmin);

        $middleware = new EnsureUserIsAdmin;

        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('OK'));
    }

    #[Test]
    public function guest_gets_403(): void
    {
        $request = Request::create('/invitations/create');
        $request->setUserResolver(fn () => null);

        $middleware = new EnsureUserIsAdmin;

        $this->expectException(HttpException::class);
        $middleware->handle($request, fn () => new Response('OK'));
    }
}
