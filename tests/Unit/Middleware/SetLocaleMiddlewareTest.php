<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetLocaleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

#[CoversClass(SetLocaleMiddleware::class)]
final class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public SetLocaleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new SetLocaleMiddleware;
    }

    #[Test]
    public function sets_locale_from_authenticated_user_settings(): void
    {
        $user = User::factory()->create([
            'settings' => ['lang' => 'sl'],
        ]);

        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertSame('sl', app()->getLocale());
    }

    #[Test]
    public function defaults_to_english_for_guest(): void
    {
        app()->setLocale('sl');

        $request = Request::create('/dashboard');

        $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertSame('sl', app()->getLocale());
    }
}
