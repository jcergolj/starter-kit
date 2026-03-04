<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Subdomain Not Found') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="{{ tailwindcss('css/app.css') }}" rel="stylesheet" />
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background: var(--color-bg); color: var(--color-text);">
    <div class="text-center max-w-md">
        <div class="mb-6">
            <div class="w-20 h-20 mx-auto rounded-full flex items-center justify-center" style="background: var(--color-danger-light); color: var(--color-danger);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
        </div>

        <h1 class="text-2xl font-bold mb-2" style="color: var(--color-text);">
            {{ __('Subdomain Not Found') }}
        </h1>

        <p class="mb-6" style="color: var(--color-text-secondary);">
            {{ __('The subdomain ":subdomain" does not exist.', ['subdomain' => $subdomain]) }}
        </p>

        <a href="{{ $mainUrl }}" class="inline-flex items-center justify-center px-6 py-3 rounded-lg font-semibold text-white transition-colors" style="background: var(--color-primary);">
            {{ __('Go to Homepage') }}
        </a>
    </div>
</body>
</html>
