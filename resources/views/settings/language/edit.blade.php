<x-layouts.app :title="__('Language')">
    <section class="w-full lg:max-w-lg mx-auto">
        @unlesshotwirenative
        <x-back-link :href="route('settings')">{{ __('Profile & Settings') }}</x-back-link>
        <x-text.heading size="xl">{{ __('Language') }}</x-text.heading>
        @endunlesshotwirenative
        <x-text.subheading>{{ __('Choose your preferred language') }}</x-text.subheading>

        <x-page-card class="my-6">
            <form action="{{ route('settings.language.update') }}" method="post" class="space-y-6" data-controller="bridge--form" data-action="turbo:submit-start->bridge--form#submitStart turbo:submit-end->bridge--form#submitEnd">
                @csrf
                @method('put')

                <div>
                    <x-form.label for="lang">{{ __('Language') }}</x-form.label>

                    <x-form.select
                        id="lang"
                        name="lang"
                        :data-error="$errors->has('lang')"
                        class="mt-2"
                    >
                        <option value="en" @selected($currentLang === 'en')>English</option>
                        <option value="sl" @selected($currentLang === 'sl')>Slovenščina</option>
                    </x-form.select>

                    <x-form.error for="lang" />
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-end">
                        <x-form.button.primary type="submit" class="w-full" data-bridge--form-target="submit" data-bridge-title="{{ __('Save') }}">{{ __('Save') }}</x-form.button.primary>
                    </div>
                </div>
            </form>
        </x-page-card>
    </section>
</x-layouts.app>
