<x-layouts.app>
    <x-slot name="header">
        <h2>{{ __('Users') }}</h2>
    </x-slot>

    <section class="w-full lg:max-w-4xl mx-auto">
        @if ($users->isEmpty())
            <p class="mt-4 text-sm">{{ __('No users found.') }}</p>
        @else
            <x-page-card class="mt-4">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Username') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="text-sm">{{ $user->username }}</td>
                                <td class="text-sm">{{ $user->name }}</td>
                                <td class="text-sm">{{ $user->email }}</td>
                                <td class="text-sm">
                                    @if ($user->isBlocked())
                                        <span class="badge badge-error">{{ __('Blocked') }}</span>
                                    @else
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-ghost">{{ __('Edit') }}</a>

                                        @if ($user->isBlocked())
                                            <form method="POST" action="{{ route('blocked-users.destroy', $user) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-form.button.secondary class="btn-sm">{{ __('Unblock') }}</x-form.button.secondary>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('blocked-users.store', $user) }}">
                                                @csrf
                                                <x-form.button.secondary class="btn-sm">{{ __('Block') }}</x-form.button.secondary>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('users.destroy', $user) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-form.button.danger class="btn-sm">{{ __('Delete') }}</x-form.button.danger>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-page-card>
        @endif
    </section>
</x-layouts.app>
