{{--
    Meerdere keuzes als chips, bv. spiergroepen bij een workout of oefening:
    <x-form.chips label="Spiergroepen" wire:model="muscle_group_ids"
                  :options="$muscleGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name, 'color' => $g->color])" />
--}}
@props(['label' => null, 'name' => null, 'options' => [], 'hint' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $model = $attributes->whereStartsWith('wire:model');
@endphp

<fieldset class="flex min-w-0 flex-col gap-1.5">
    @if ($label) <legend class="mb-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</legend> @endif
    <div class="flex flex-wrap gap-2">
        @foreach ($options as $opt)
            @php $color = $opt['color'] ?? '#1d63c5'; @endphp
            <label class="cursor-pointer rounded-full px-3.5 py-2 text-sm font-semibold ring-1 ring-inset ring-slate-300 transition select-none
                          has-checked:bg-(--chip) has-checked:text-white has-checked:ring-transparent has-focus-visible:outline-2 has-focus-visible:outline-accent-400 dark:ring-line-dark"
                   style="--chip: {{ $color }}">
                <input type="checkbox" value="{{ $opt['value'] }}" class="sr-only" {{ $model }}
                       @if ($name && $model->isEmpty()) name="{{ $name }}[]" @checked(in_array($opt['value'], old($name, []))) @endif>
                {{ $opt['label'] }}
            </label>
        @endforeach
    </div>
    @if ($hint) <x-form.hint>{{ $hint }}</x-form.hint> @endif
    @if ($name) <x-form.error :name="$name" /> <x-form.error :name="$name.'.*'" /> @endif
</fieldset>
