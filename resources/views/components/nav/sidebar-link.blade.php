{{-- Sidebar-item: md = alleen icoon (met tooltip), lg = icoon + tekst --}}
@props(['href', 'icon', 'active' => false])

<a href="{{ $href }}" wire:navigate title="{{ $slot }}" @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'group relative flex items-center justify-center gap-3 rounded-xl px-3 py-2.5 font-semibold transition lg:justify-start lg:px-3.5',
       'bg-white/15 text-white' => $active,
       'text-brand-200 hover:bg-white/10 hover:text-white' => ! $active,
   ]) }}>
    @if ($active)
        <span class="absolute inset-y-2.5 -left-3 w-1 rounded-r bg-accent-400 lg:-left-4" aria-hidden="true"></span>
    @endif
    <x-icon :name="$icon" class="size-5.5" />
    <span class="hidden lg:inline">{{ $slot }}</span>
</a>
