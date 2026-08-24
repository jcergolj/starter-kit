@props(['class' => ''])

<label class="input w-full join-item has-[[data-error]]:input-error {{ $class }}" data-controller="password-reveal">
    <x-heroicon-o-key aria-hidden="true" class="size-[1em] opacity-50" />

    <input {{ $attributes->merge(['type' => 'password', 'data-password-reveal-target' => 'input']) }} />

    <button
        class="tooltip mr-1 flex h-full items-center justify-center"
        aria-hidden="true"
        data-tip="{{ __('Reveal') }}"
        type="button"
        data-action="password-reveal#toggle turbo:before-cache@document->password-reveal#reset"
    >
        <span class="grid grid-cols-1 place-items-center">
            <x-heroicon-o-eye
                aria-hidden="true"
                class="hover:text-primary col-start-1 row-start-1 size-[1.25em] opacity-50 [:where([data-password-reveal-revealed-value=true]_&)]:hidden"
            />
            <x-heroicon-o-eye-slash
                aria-hidden="true"
                class="hover:text-primary col-start-1 row-start-1 hidden size-[1.25em] opacity-50 [:where([data-password-reveal-revealed-value=true]_&)]:block!"
            />
        </span>

        <span class="sr-only">{{ __('Reveal') }}</span>
    </button>
</label>
