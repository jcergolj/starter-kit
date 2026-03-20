<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(EnsureUserIsNotBlocked::class)]
class EnsureUserIsNotBlockedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blocked_user_gets_403(): void
    {
        $user = User::factory()->blocked()->create();
        $request = Request::create('/dashboard');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $middleware = new EnsureUserIsNotBlocked;

        $this->expectException(HttpException::class);
        $middleware->handle($request, function () {
            return new Response('OK');
        });
    }

    #[Test]
    public function non_blocked_user_passes_through(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/dashboard');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $nextCalled = false;
        $middleware = new EnsureUserIsNotBlocked;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function guest_passes_through(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(function () {
            return null;
        });

        $nextCalled = false;
        $middleware = new EnsureUserIsNotBlocked;
        $response = $middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertSame(200, $response->getStatusCode());
    }
}
