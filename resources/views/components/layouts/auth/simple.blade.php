@props(['transitions' => true, 'scalable' => false, 'title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if (session('theme')) data-theme="{{ session('theme') }}" @endif  >
    <head>
        @include('partials.head', [
            'transitions' => $transitions,
            'scalable' => $scalable,
            'title' => $title,
        ])
    </head>
    <body @class(["min-h-screen antialiased", "hotwire-native" => Turbo::isHotwireNativeVisit()]) style="background: var(--color-bg);">
        <div class="flex min-h-screen flex-col items-center justify-center px-2 py-12">
            <x-in-app-notifications::notification />

            <div class="w-full max-w-lg px-4">
                <div class="text-center mb-8">
                    <a href="{{ route('home') }}" class="inline-flex flex-col items-center group">
                        <div class="auth-logo-icon">
                            <x-app-logo-icon class="w-9 h-9 text-white" />
                        </div>
                        <span class="font-display text-xl" style="color: var(--color-text);">{{ config('app.name', 'Laravel') }}</span>
                    </a>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </div>

                <div class="auth-card">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
