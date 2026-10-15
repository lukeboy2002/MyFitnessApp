{{-- <x-card title="Profielfoto" description="JPG of PNG, max 2 MB"> ... <x-slot:footer>...</x-slot:footer></x-card> --}}
@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'min-w-0 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-panel-dark dark:ring-line-dark']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 px-5 pt-5 sm:px-6">
            <div>
                @if ($title)
                    <h2 class="font-display text-xl font-bold uppercase tracking-wide">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['px-5 py-5 sm:px-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-3 rounded-b-2xl border-t border-slate-200 bg-slate-50 px-5 py-3.5 sm:px-6 dark:border-line-dark dark:bg-white/[.03]">
            {{ $footer }}
        </footer>
    @endisset
</section>
