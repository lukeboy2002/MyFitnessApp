@php
    use App\Enums\ExperienceLevel;
    use App\Enums\FitnessGoal;

    $starter = $this->starter;
    $rest = auth()->user()->default_rest_seconds ?? $starter->rest;
@endphp

<div class="mx-auto max-w-3xl">
    <x-page-header title="Welkom {{ str(auth()->user()->name)->before(' ') }}" description="Fijn dat je er bent. Vertel wat je wilt bereiken, dan zet ik een eerste workout voor je klaar." />

    <x-card class="mb-5" title="Waar train je voor?">
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (FitnessGoal::cases() as $option)
                <label wire:key="goal-{{ $option->value }}" @class(['flex cursor-pointer items-center gap-3 rounded-xl px-4 py-3 ring-1 transition',
                    'bg-brand-50 font-semibold ring-brand-400 dark:bg-brand-500/10 dark:ring-brand-400/60' => $goal === $option->value,
                    'ring-slate-200 hover:bg-slate-50 dark:ring-line-dark dark:hover:bg-white/5' => $goal !== $option->value])>
                    <input type="radio" wire:model.live="goal" value="{{ $option->value }}" class="size-4 accent-brand-700">
                    {{ $option->label() }}
                </label>
            @endforeach
        </div>

        <div class="mt-5">
            <p class="mb-2 text-sm font-semibold">Hoe lang train je al?</p>
            <div class="inline-flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 dark:bg-white/5">
                @foreach (ExperienceLevel::cases() as $option)
                    <button type="button" wire:click="$set('experience', '{{ $option->value }}')"
                            @class(['rounded-xl px-3 py-2 text-sm font-semibold transition',
                                    'bg-white text-ink shadow-sm dark:bg-white/15 dark:text-white' => $experience === $option->value,
                                    'text-slate-600 hover:text-ink dark:text-slate-300' => $experience !== $option->value])>
                        {{ str($option->label())->before(' (') }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-card>

    <x-card class="mb-5" style="--c: {{ $starter->color }}" :padding="false">
        <div class="flex items-start gap-3 px-5 pt-5 sm:px-6">
            <span class="mt-1 h-10 w-1.5 shrink-0 rounded-full bg-(--c)"></span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Jouw eerste workout</p>
                <h2 class="font-display text-2xl font-bold uppercase">{{ $starter->name }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $starter->description }}</p>
            </div>
        </div>

        @if ($starter->items->isEmpty())
            <p class="px-5 py-5 text-slate-500 sm:px-6 dark:text-slate-400">De standaardoefeningen voor deze workout ontbreken. Maak zelf een workout via Workouts.</p>
        @else
            <ul class="mt-4 divide-y divide-slate-100 dark:divide-line-dark">
                @foreach ($starter->items as $item)
                    <li wire:key="starter-{{ $item['exercise']->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                            @if ($picture = $item['exercise']->imageUrl())
                                <img src="{{ $picture }}" alt="" loading="lazy" class="size-full object-contain p-0.5">
                            @else
                                <x-icon :name="$item['exercise']->type->icon()" class="size-5 text-slate-500" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1 truncate font-semibold">{{ $item['exercise']->name }}</span>
                        <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">
                            {{ $item['minutes'] ? $item['minutes'].' min' : $item['sets'].' × '.$item['reps'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="flex gap-2 border-t border-slate-100 px-5 py-4 text-sm text-slate-600 sm:px-6 dark:border-line-dark dark:text-slate-300">
                <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-brand-600 dark:text-brand-300" />
                Rust {{ $rest % 60 ? $rest.' seconden' : ($rest / 60).' '.($rest === 60 ? 'minuut' : 'minuten') }} tussen sets. Kies een gewicht waarmee de laatste herhalingen zwaar zijn; je vult het in tijdens de training.
            </p>
        @endif
    </x-card>

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <x-button variant="ghost" wire:click="skip" loading="skip">Overslaan</x-button>
        @if ($starter->items->isNotEmpty())
            <x-button icon="check" wire:click="create" loading="create">Workout aanmaken</x-button>
        @endif
    </div>

    <div class="mt-8 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['play', 'Quickstart', 'Begin direct een training en voeg oefeningen toe terwijl je bezig bent.'],
            ['calendar', 'Kalender', 'Plan je workouts in, dan zie je op het dashboard wat er vandaag op het menu staat.'],
            ['medal', 'Records en badges', 'Elke training telt mee voor je records, badges en je weekdoel.'],
        ] as [$icon, $title, $text])
            <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
                <x-icon :name="$icon" class="size-5 text-fire-500" />
                <p class="mt-2 font-semibold">{{ $title }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</div>
