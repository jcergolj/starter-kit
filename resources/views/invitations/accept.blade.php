<x-layouts.auth :title="__('Accept Invitation')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Accept Invitation')" :description="__('Complete your registration below.')" />

        <form id="accept-invitation-form" method="POST" action="{{ route('accept.invitations.store', $invitation->token) }}" class="flex flex-col gap-6">
            @csrf

            <div>
                <x-form.label for="email">{{ __('Email address') }}</x-form.label>
                <x-form.text-input id="email" type="email" name="email" :value="$invitation->email" readonly class="mt-2" />
            </div>

            <div>
                <x-form.label for="name">{{ __('Name') }}</x-form.label>
                <x-form.text-input id="name" type="text" name="name" :value="old('name')" required autofocus class="mt-2" />
                <x-form.error for="name" />
            </div>

            <div>
                <x-form.label for="username">{{ __('Username') }}</x-form.label>
                <x-form.text-input id="username" type="text" name="username" :value="old('username')" required class="mt-2" />
                <x-form.error for="username" />
            </div>

            <div>
                <x-form.label for="password">{{ __('Password') }}</x-form.label>
                <x-form.password-input id="password" name="password" required class="mt-2" />
                <x-form.error for="password" />
            </div>

            <div>
                <x-form.label for="password_confirmation">{{ __('Confirm password') }}</x-form.label>
                <x-form.password-input id="password_confirmation" name="password_confirmation" required class="mt-2" />
            </div>

            <x-form.button.primary type="submit" class="w-full">
                {{ __('Complete Registration') }}
            </x-form.button.primary>
        </form>
    </div>
</x-layouts.auth>
