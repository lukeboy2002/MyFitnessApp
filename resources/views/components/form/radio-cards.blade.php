{{--
    Keuze als grote tegels, bv. het type oefening:
    <x-form.radio-cards label="Type" wire:model.live="type" :options="[
        'strength' => ['label' => 'Kracht', 'icon' => 'dumbbell', 'description' => 'Sets, herhalingen, gewicht'],
        'cardio'   => ['label' => 'Cardio', 'icon' => 'run', 'description' => 'Tijd, afstand, intensiteit'],
    ]" />
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'columns' => 2])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $model = $attributes->whereStartsWith('wire:model');
@endphp

<fieldset class="flex min-w-0 flex-col gap-1.5">
    @if ($label) <legend class="mb-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</legend> @endif
    <div @class(['grid gap-3', 'grid-cols-2' => $columns == 2, 'grid-cols-1 sm:grid-cols-3' => $columns == 3])>
        @foreach ($options as $value => $opt)
            <label class="relative flex cursor-pointer flex-col gap-1 rounded-2xl bg-white p-4 ring-1 ring-slate-300 transition
                          has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600 has-focus-visible:outline-2 has-focus-visible:outline-accent-400
                          dark:bg-white/5 dark:ring-line-dark dark:has-checked:bg-brand-500/15 dark:has-checked:ring-brand-400">
                <input type="radio" value="{{ $value }}" class="sr-only" {{ $model }}
                       @if ($name && $model->isEmpty()) name="{{ $name }}" @checked(old($name) === (string) $value) @endif>
                @isset($opt['icon'])
                    <x-icon :name="$opt['icon']" class="size-6 text-brand-700 dark:text-brand-300" />
                @endisset
                <span class="font-semibold">{{ $opt['label'] ?? $opt }}</span>
                @isset($opt['description'])
                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $opt['description'] }}</span>
                @endisset
            </label>
        @endforeach
    </div>
    @if ($name) <x-form.error :name="$name" /> @endif
</fieldset>
