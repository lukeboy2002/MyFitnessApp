{{--
    Groepeert velden met titel links en velden rechts (op lg), onder elkaar op mobiel.
    <x-form.section title="Doelen" description="Wat wil je per keer halen?"> ...velden... </x-form.section>
--}}
@props(['title', 'description' => null, 'columns' => 2])

<div {{ $attributes->merge(['class' => 'grid gap-x-8 gap-y-4 border-t border-slate-200 py-6 first:border-t-0 first:pt-0 lg:grid-cols-3 dark:border-line-dark']) }}>
    <div>
        <h3 class="font-display text-lg font-bold uppercase tracking-wide">{{ $title }}</h3>
        @if ($description) <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p> @endif
    </div>
    <div @class(['grid min-w-0 gap-4 lg:col-span-2', 'sm:grid-cols-2' => $columns == 2, 'sm:grid-cols-3' => $columns == 3])>
        {{ $slot }}
    </div>
</div>
