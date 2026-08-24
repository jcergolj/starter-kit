@props(['icon' => null, 'current' => false, 'as' => 'a'])

@if ($as === 'a')
    <a {{ $attributes->merge(['class' => 'nav-item flex items-center px-3 py-2 text-sm font-medium rounded-md ' . ($current ? 'active' : '')]) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="mr-2 h-4 w-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => 'nav-item flex items-center px-3 py-2 text-sm font-medium rounded-md ' . ($current ? 'active' : '')]) }}>
        @if ($icon)
            <x-dynamic-component :component="'heroicon-o-'.$icon" class="mr-2 h-4 w-4" />
        @endif
        {{ $slot }}
    </button>
@endif
