<x-layouts.auth :title="__('Verify email')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Verify your email')" :description="__('Please verify your email address by clicking on the link we just emailed to you.')" />

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-2xl border border-[var(--color-primary-light)] bg-[var(--color-accent-light)] p-4 text-center text-sm font-medium text-[var(--color-primary-hover)]">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="flex flex-col gap-4">
            <form id="resend-verification-form" action="{{ route('verification.send') }}" method="post">
                @csrf

                <button type="submit" class="btn btn-primary w-full">
                    {{ __('Resend verification email') }}
                </button>
            </form>

            <form action="{{ route('logout') }}" method="post" class="text-center">
                @csrf

                <button type="submit" class="btn btn-link">
                    {{ __('Log out') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.auth>
