<x-layouts.app>
    <x-slot name="header">
        <h2>{{ __('Edit user') }}</h2>
    </x-slot>

    <section class="w-full lg:max-w-xl mx-auto">
        <form method="POST" action="{{ route('users.update', $user) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            <div>
                <x-form.label for="name">{{ __('Name') }}</x-form.label>
                <x-form.text-input id="name" type="text" name="name" :value="old('name', $user->name)" :data-error="$errors->has('name')" required class="mt-2" />
                <x-form.error for="name" />
            </div>

            <div>
                <x-form.label for="username">{{ __('Username') }}</x-form.label>
                <x-form.text-input id="username" type="text" name="username" :value="old('username', $user->username)" :data-error="$errors->has('username')" required class="mt-2" />
                <x-form.error for="username" />
            </div>

            <div>
                <x-form.label for="email">{{ __('Email address') }}</x-form.label>
                <x-form.text-input id="email" type="email" name="email" :value="old('email', $user->email)" :data-error="$errors->has('email')" required class="mt-2" />
                <x-form.error for="email" />
            </div>

            <div class="flex items-center gap-4">
                <x-form.button.primary type="submit">{{ __('Save') }}</x-form.button.primary>
                <a href="{{ route('users.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</x-layouts.app>
