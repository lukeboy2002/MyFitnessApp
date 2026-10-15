@props(['on'])

<div x-data="{ shown: false, timeout: null }"
     x-init="$wire.on('{{ $on }}', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => shown = false, 2500) })"
     x-show="shown" x-transition.opacity.duration.300ms x-cloak
    {{ $attributes->merge(['class' => 'mr-auto flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400']) }}>
    <x-icon name="check" class="size-4"/> {{ $slot }}
</div>
