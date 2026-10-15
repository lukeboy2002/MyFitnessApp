{{-- <x-page-header title="Workouts" description="Je trainingsschema's"><x-button icon="plus">Nieuw</x-button></x-page-header> --}}
@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        <h1 class="font-display text-[34px] leading-none font-extrabold uppercase text-balance">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
