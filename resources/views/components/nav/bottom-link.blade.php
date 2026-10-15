{{-- Item in de mobiele menubalk. highlight = de ronde Quickstart-knop in het midden --}}
@props(['href', 'icon', 'active' => false, 'highlight' => false])

<a href="{{ $href }}" wire:navigate @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'flex min-h-12 flex-col items-center justify-end gap-0.5 pb-1 text-[11px] font-semibold transition',
       'text-brand-700 dark:text-brand-300' => $active && ! $highlight,
       'text-slate-500 dark:text-slate-400' => ! $active && ! $highlight,
       'text-fire-500' => $highlight,
   ]) }}>
    @if ($highlight)
        <span class="-mt-7 mb-0.5 grid size-14 place-items-center rounded-full border-4 border-white bg-linear-to-br from-accent-400 to-fire-500 text-white shadow-lg shadow-fire-500/40 dark:border-panel-dark">
            <x-icon :name="$icon" class="size-6" />
        </span>
    @else
        <x-icon :name="$icon" class="size-6" />
    @endif
    {{ $slot }}
</a>
