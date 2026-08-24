<div class="mb-2 flex items-center justify-center">
    <a {{ $attributes->merge(['class' => 'btn btn-link']) }}>
        <x-heroicon-o-arrow-left class="size-4" />

        {{ $slot }}
    </a>
</div>
