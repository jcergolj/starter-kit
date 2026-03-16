<x-layouts.app :title="__('Users')">
    <section class="w-full lg:max-w-4xl mx-auto">
        <x-text.heading size="xl">{{ __('Users') }}</x-text.heading>
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
                                            <a href="{{ route('blocked-users.destroy', $user) }}" class="btn btn-secondary btn-sm" data-turbo-method="delete" data-turbo-confirm="{{ __('Are you sure you want to unblock this user?') }}">{{ __('Unblock') }}</a>
                                        @else
                                            <a href="{{ route('blocked-users.store', $user) }}" class="btn btn-secondary btn-sm" data-turbo-method="post" data-turbo-confirm="{{ __('Are you sure you want to block this user?') }}">{{ __('Block') }}</a>
                                        @endif

                                        <a href="{{ route('users.destroy', $user) }}" class="btn btn-error btn-sm" data-turbo-method="delete" data-turbo-confirm="{{ __('Are you sure you want to delete this user?') }}">{{ __('Delete') }}</a>
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
