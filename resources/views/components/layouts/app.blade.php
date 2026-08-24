<x-layouts.app.header :title="$title ?? null">
    <div class="w-full p-6 lg:p-8">
        <div class="flex h-full w-full flex-1 flex-col gap-4">{{ $slot }}</div>
    </div>
</x-layouts.app.header>
