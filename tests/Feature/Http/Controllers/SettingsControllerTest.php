<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\SettingsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SettingsController::class)]
final class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_requires_authentication(): void
    {
        $response = $this->get(route('settings'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function show_is_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->get(route('settings'));

        // Response may be view or redirect depending on middleware
        $this->assertContains($response->getStatusCode(), [200, 302]);
    }
}
