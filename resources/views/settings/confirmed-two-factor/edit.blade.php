<x-layouts.app :title="__('Enable Two-Factor Authentication')">
    <section class="mx-auto w-full lg:max-w-xl">
        <x-back-link :href="route('settings.two-factor.edit')">{{ __('Two-factor authentication') }}</x-back-link>
        <x-text.heading size="xl">{{ __('Enable Two-Factor Authentication') }}</x-text.heading>

        <x-page-card class="my-6">
            <x-turbo::frame
                id="two-factor-enable"
                class="relative flex flex-col items-start justify-start space-y-4 rounded-xl"
            >
                <div class="badge badge-soft badge-error">{{ __('Disabled') }}</div>

                <input
                    type="checkbox"
                    class="peer sr-only"
                    id="continue"
                    @if (old('continue')) checked @endif
                    form="confirm-two-factor-form"
                    name="continue"
                />

                <div class="flex flex-col items-start justify-start space-y-4 peer-checked:hidden">
                    <p class="text-base-content dark:text-base-content/70 -translate-y-1 text-sm">
                        {{ __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.') }}
                    </p>

                    <div class="border-base-content/20 flex h-full items-center justify-center rounded-xl border p-4">
                        <div class="rounded bg-white p-3">{!! $qrCodeSvg !!}</div>
                    </div>

                    <label for="continue" role="button" class="btn btn-primary">Continue</label>

                    <div class="divider text-base-content/70 text-sm">{{ __('or, enter the code manually') }}</div>

                    <div class="join group" data-controller="clipboard">
                        <input
                            class="input join-item"
                            name="setup_code"
                            type="text"
                            data-clipboard-target="source"
                            value="{{ $setupCode }}"
                            readonly
                        />

                        <button type="button" class="btn join-item rounded-r-full" data-action="clipboard#copy">
                            <x-heroicon-o-clipboard class="text-content-base h-4 w-4 group-data-[clipboard-copied-value=true]:hidden" />
                            <x-heroicon-o-check class="hidden h-4 w-4 text-green-500 group-data-[clipboard-copied-value=true]:inline" />

                            <span class="sr-only">{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>

                <form
                    id="confirm-two-factor-form"
                    action="{{ route('settings.confirmed-two-factor.update') }}"
                    method="post"
                    class="block hidden w-full flex-col items-start justify-start space-y-4 peer-checked:flex"
                >
                    @csrf
                    @method('PUT')

                    <p class="text-base-content dark:text-base-content/70 -translate-y-1 text-sm">
                        {{ __('Enter the 6-digit code from your authenticator app.') }}
                    </p>

                    <div class="w-full">
                        <x-form.label for="code" class="sr-only">{{ __('Code') }}</x-form.label>

                        <x-form.otp-input
                            id="code"
                            name="code"
                            :value="old('code')"
                            :data-error="$errors->has('code')"
                        />

                        <x-form.error for="code" />
                    </div>

                    <div class="w-full">
                        <div class="grid grid-cols-2 gap-4">
                            <label for="continue" role="button" class="btn btn-block btn-cancel">{{ __('Cancel') }}</label>

                            <button type="submit" class="btn btn-block btn-primary">{{ __('Confirm') }}</button>
                        </div>
                    </div>
                </form>
            </x-turbo::frame>
        </x-page-card>
    </section>
</x-layouts.app>
