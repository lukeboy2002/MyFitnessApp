{{-- Sidebar-item: md = alleen icoon (met tooltip), lg = icoon + tekst. badge = teller, bv. nieuwe gebruikers --}}
@props(['href', 'icon', 'active' => false, 'badge' => 0])

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
    @if ($badge)
        <span class="absolute right-1.5 top-1 min-w-5 rounded-full bg-fire-500 px-1.5 text-center text-xs font-bold leading-5 text-white tabular-nums lg:static lg:ms-auto"
              title="{{ $badge }} nieuw">{{ $badge }}</span>
    @endif
</a>
