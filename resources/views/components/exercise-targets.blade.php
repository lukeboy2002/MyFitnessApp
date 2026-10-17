{{--
    Doelvelden van een oefening, zoals in een workout of bij toevoegen tijdens een training:
    <x-exercise-targets :type="$exercise->type" :item="$items[$i]" model="items.{{ $i }}" />
--}}
@props(['type', 'item', 'model'])

@php($Type = \App\Enums\ExerciseType::class)
@php($metric = \App\Enums\CardioMetric::tryFrom((string) ($item['metric'] ?? '')))

<div {{ $attributes->class('grid grid-cols-2 gap-3 sm:grid-cols-4') }}>
    @if ($type->usesSets())
        <x-form.input label="Sets" type="number" wire:model="{{ $model }}.sets" inputmode="numeric" min="1" />
    @endif
    @if ($type->usesReps())
        <x-form.input label="Herhalingen" type="number" wire:model="{{ $model }}.reps" inputmode="numeric" min="1" />
    @endif
    @if ($type->usesWeight())
        <x-form.input label="Gewicht" wire:model="{{ $model }}.weight" inputmode="decimal" suffix="kg" />
    @endif
    @if ($type->usesExtraWeight())
        <x-form.input label="Extra gewicht" wire:model="{{ $model }}.weight" inputmode="decimal" suffix="kg" placeholder="0" />
    @endif
    @if ($type === $Type::Duration)
        <x-form.input label="Tijd per set" type="number" wire:model="{{ $model }}.seconds" inputmode="numeric" suffix="sec" />
    @endif
    @if ($type->usesSets())
        <x-form.input label="Rust" type="number" wire:model="{{ $model }}.rest" inputmode="numeric" suffix="sec" />
    @endif
    @if ($type === $Type::Cardio)
        <x-form.input label="Tijd" type="number" wire:model="{{ $model }}.minutes" inputmode="numeric" suffix="min" />
        <x-form.select label="Instelling" wire:model.live="{{ $model }}.metric" placeholder="Geen" :options="\App\Enums\CardioMetric::options()" />
        @if ($metric)
            <x-form.input :label="$metric->label()" wire:model="{{ $model }}.metric_value" inputmode="decimal" :suffix="$metric->unit()" />
        @endif
    @endif
    <x-form.input label="Notitie" wire:model="{{ $model }}.notes" placeholder="Bijv. stoel op stand 4" class="col-span-2 sm:col-span-4" />
</div>
