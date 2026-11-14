{{--
    Taalkeuze. Ingelogd wordt de keuze in je profiel bewaard, als gast in de sessie (route locale.update).
    <x-language-switcher />                    vlaggen, voor de blauwe band op de gastpagina's
    <x-language-switcher variant="segmented" /> knoppen met de naam van de taal, voor de profielpagina
--}}
@props(['variant' => 'compact'])

@php($current = app()->getLocale())

<form method="POST" aria-label="{{ __('Language') }}" {{ $attributes->class([
    'inline-flex flex-wrap rounded-xl p-1',
    'gap-1 bg-white/10 ring-1 ring-white/20 backdrop-blur' => $variant === 'compact',
    'bg-slate-100 dark:bg-white/5' => $variant === 'segmented',
]) }}>
    @csrf
    @foreach (\App\Support\Locale::available() as $code => $name)
        <button type="submit" formaction="{{ route('locale.update', $code) }}" lang="{{ $code }}" title="{{ $name }}"
                @if ($current === $code) aria-current="true" @endif
                @class([
                    'rounded-lg transition',
                    'flex min-h-9 min-w-11 items-center justify-center px-2' => $variant === 'compact',
                    'bg-white/90 shadow-sm' => $variant === 'compact' && $current === $code,
                    'opacity-60 hover:bg-white/15 hover:opacity-100' => $variant === 'compact' && $current !== $code,
                    'flex min-h-10 items-center px-3.5 text-sm font-semibold' => $variant === 'segmented',
                    'bg-white text-brand-700 shadow-sm dark:bg-brand-500 dark:text-white' => $variant === 'segmented' && $current === $code,
                    'text-slate-500 hover:text-ink dark:text-slate-400 dark:hover:text-white' => $variant === 'segmented' && $current !== $code,
                ])>
            @if ($variant === 'compact')
                <x-flag :code="$code" class="h-4 w-6 ring-1 ring-black/10"/>
                <span class="sr-only">{{ $name }}</span>
            @else
                {{ $name }}
            @endif
        </button>
    @endforeach
</form>
