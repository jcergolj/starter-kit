<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Settings;

use App\Http\Controllers\Settings\LanguageController;
use App\Http\Requests\SaveLanguageRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LanguageController::class)]
final class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function edit_has_auth_and_verified_middleware(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.language.edit'));

        $response->assertMiddlewareIsApplied('auth');
        $response->assertMiddlewareIsApplied('verified');
    }

    #[Test]
    public function edit_displays_language_form(): void
    {
        $this->withoutMiddleware(\HotwiredLaravel\Hotreload\Http\Middleware\HotreloadMiddleware::class);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.language.edit'));

        $response->assertOk()
            ->assertViewIs('settings.language.edit')
            ->assertViewHasForm('id="update-language-form"', 'PUT', route('settings.language.update'))
            ->assertFormHasCSRF()
            ->assertFormHasDropdown('lang')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function update_changes_user_language(): void
    {
        $user = User::factory()->create([
            'settings' => ['lang' => 'en'],
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.language.edit'))
            ->put(route('settings.language.update'), [
                'lang' => 'sl',
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertSame('sl', $user->settings->lang);
    }

    #[Test]
    public function controller_has_form_request(): void
    {
        $this->put(route('settings.language.update'));

        $this->assertContainsFormRequest(SaveLanguageRequest::class);
    }
}
