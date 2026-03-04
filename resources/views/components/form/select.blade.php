<select {{ $attributes->merge([
    'class' => 'w-full select data-error:select-error',
]) }}>
    {{ $slot }}
</select>
