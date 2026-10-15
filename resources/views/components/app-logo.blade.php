@props(['text' => true])

<span {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <img src="{{ asset('storage/assets/logo.png') }}" alt="{{ config('app.name') }}" class="size-12 shrink-0">
    @if ($text)
        <span class="font-display leading-none">
            <span class="block text-[22px] font-extrabold uppercase">Lukeboy</span>
            <span
                class="mt-0.5 block font-sans text-[11px] font-semibold tracking-[.14em] text-accent-400">FITNESS</span>
        </span>
    @endif
</span>
