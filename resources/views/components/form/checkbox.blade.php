@props(['id' => null, 'label' => null, 'description' => null])

<label class="flex items-center gap-3 cursor-pointer group">
    <input {{ $attributes->merge(['id' => $id, 'type' => 'checkbox', 'class' => 'checkbox checkbox-primary border-[3px] border-base-content/40 rounded w-6 h-6 transition-all duration-200 ease-in-out checked:border-primary checked:bg-primary checked:text-white hover:border-primary focus:ring-2 focus:ring-primary/40 focus:ring-offset-1 focus:ring-offset-base-100 shadow-sm']) }} />
    <span class="text-sm text-base-content/80 group-hover:text-base-content transition-colors">{{ $label }}</span>
</label>
