{{--
    <x-theme-toggle />                    icoonknop, wisselt light → dark → system
    <x-theme-toggle variant="sidebar" />  sidebar-item met label (label verborgen op md)
    <x-theme-toggle variant="segmented" /> drie knoppen naast elkaar, voor de profielpagina
--}}
@props(['variant' => 'icon'])

@if ($variant === 'segmented')
    <div {{ $attributes->merge(['class' => 'inline-flex rounded-xl bg-slate-100 p-1 dark:bg-white/5']) }} role="radiogroup" aria-label="Thema">
        @foreach (['light' => ['sun', 'Light'], 'dark' => ['moon', 'Dark'], 'system' => ['system', 'System']] as $mode => [$icon, $label])
            <button type="button" role="radio" x-on:click="$store.theme.set('{{ $mode }}')"
                    :aria-checked="$store.theme.mode === '{{ $mode }}'"
                    :class="$store.theme.mode === '{{ $mode }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-brand-500 dark:text-white' : 'text-slate-500 hover:text-ink dark:text-slate-400 dark:hover:text-white'"
                    class="flex min-h-10 items-center gap-2 rounded-lg px-3.5 text-sm font-semibold transition">
                <x-icon :name="$icon" class="size-4" /> {{ $label }}
            </button>
        @endforeach
    </div>
@else
    <button type="button" x-data x-on:click="$store.theme.cycle()"
            :aria-label="'Thema: ' + $store.theme.mode + ', klik om te wisselen'" title="Thema wisselen"
            {{ $attributes->merge(['class' => $variant === 'sidebar'
                ? 'group relative flex items-center justify-center gap-3 rounded-xl px-3 py-2.5 font-semibold text-brand-200 transition hover:bg-white/15 hover:text-white lg:justify-start lg:px-3.5'
                : 'grid size-11 place-items-center rounded-xl transition hover:bg-white/15']) }}>
        <x-icon name="sun" class="size-5.5" x-show="$store.theme.mode === 'light'" />
        <x-icon name="moon" class="size-5.5" x-show="$store.theme.mode === 'dark'" x-cloak />
        <x-icon name="system" class="size-5.5" x-show="$store.theme.mode === 'system'" x-cloak />
        @if ($variant === 'sidebar')
            <span class="hidden lg:inline">Thema: <span x-text="{ light: 'Light', dark: 'Dark', system: 'System' }[$store.theme.mode]"></span></span>
        @endif
    </button>
@endif
