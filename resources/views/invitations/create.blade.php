<x-layouts.app :title="__('Send invitation')">
    <section class="w-full lg:max-w-xl mx-auto">
        <x-text.heading size="xl">{{ __('Send invitation') }}</x-text.heading>
        <form id="create-invitation" method="POST" action="{{ route('invitations.store') }}" class="flex flex-col gap-6">
            @csrf
            <div>
                <x-form.label for="email">{{ __('Email address') }}</x-form.label>
                <x-form.text-input id="email" type="email" name="email" :value="old('email')" :data-error="$errors->has('email')" required autofocus placeholder="email@example.com" class="mt-2" />
                <x-form.error for="email" />
            </div>
            <x-form.button.primary type="submit" class="w-full">{{ __('Send invitation') }}</x-form.button.primary>
        </form>

        @if ($pendingInvitations->isNotEmpty())
            <x-page-card class="mt-8">
                <x-text.heading size="lg" class="mb-4">{{ __('Pending invitations') }}</x-text.heading>
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Email') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingInvitations as $invitation)
                            <tr>
                                <td class="text-sm">{{ $invitation->email }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('invitations.destroy', $invitation) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-form.button.danger class="btn-sm">{{ __('Revoke') }}</x-form.button.danger>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-page-card>
        @endif
    </section>
</x-layouts.app>
