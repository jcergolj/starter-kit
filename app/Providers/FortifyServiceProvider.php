<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Authentication\Actions\ResetUserPassword;
use App\Features\Authentication\Responses\PasswordResetLinkResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        $this->configureFotifyActions();
        $this->configureFortifyViews();
        $this->configureRateLimiting();
    }

    private function configureFortifyViews(): void
    {
        Fortify::twoFactorChallengeView(function () {
            return view('authentication::auth.two-factor-challenge');
        });
        Fortify::confirmPasswordView(function () {
            return view('authentication::auth.confirm-password');
        });
        Fortify::loginView(function () {
            return view('authentication::auth.login');
        });
        Fortify::verifyEmailView(function () {
            return view('authentication::auth.verify-email');
        });
        Fortify::resetPasswordView(function () {
            return view('authentication::auth.reset-password');
        });
        Fortify::requestPasswordResetLinkView(function () {
            return view('authentication::auth.forgot-password');
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $databaseIdentity = config('app.single_db_per_app') ? 'single' : $request->session()->get('tenant_host');

            return Limit::perMinute(5)->by($databaseIdentity.'|'.$request->session()->get('login.id'));
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        Route::matched(function (RouteMatched $event): void {
            if ($event->route->named('password.email')) {
                $event->route->middleware('throttle:password-reset');
            }
        });
    }

    private function configureFotifyActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
    }
}
