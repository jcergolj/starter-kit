<x-layouts.auth :title="__('Verify email')">
    <div class="flex flex-col gap-6">
        <div class="text-center">
            <h2 class="mb-2 text-xl font-semibold" style="color: var(--color-text)">{{ __('Verify your email') }}</h2>
            <p style="color: var(--color-text-secondary)">
                {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div
                class="rounded-lg p-4 text-center"
                style="background: var(--color-success-light); color: var(--color-success)"
            >
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="flex flex-col gap-4">
            <form id="resend-verification-form" action="{{ route('verification.send') }}" method="post">
                @csrf

                <button
                    type="submit"
                    class="btn-primary w-full rounded-lg px-4 py-3 font-semibold text-white transition-colors"
                >
                    {{ __('Resend verification email') }}
                </button>
            </form>

            <form action="{{ route('logout') }}" method="post" class="text-center">
                @csrf

                <button
                    type="submit"
                    class="text-sm font-medium hover:underline"
                    style="color: var(--color-text-secondary)"
                >
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>
