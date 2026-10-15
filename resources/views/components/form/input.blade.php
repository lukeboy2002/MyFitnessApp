{{--
    <x-form.input label="E-mailadres" type="email" name="email" icon="mail" required autofocus />
    <x-form.input label="Gewicht" wire:model="weight" suffix="kg" inputmode="decimal" />
    <x-form.input label="Duur" wire:model.blur="minutes" type="number" suffix="min" hint="Totale tijd op het apparaat" />

    name wordt automatisch afgeleid van wire:model, zodat validatiefouten vanzelf verschijnen.
--}}
@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'hint' => null,
    'icon' => null,
    'prefix' => null,
    'suffix' => null,
    'required' => false,
])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
    $invalid = $name && $errors->has($name);
    $c = config('ui.control');
@endphp

<x-form.field :$label :$name :$id :$hint :$required :class="$attributes->get('class')">
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
        @elseif ($prefix)
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500">{{ $prefix }}</span>
        @endif

        <input type="{{ $type }}"
               @if ($id) id="{{ $id }}" @endif
               @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
               @if ($required) required @endif
               @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error"
               @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
               {{ $attributes->except('class')->class([
                   $c['base'],
                   $c['invalid'] => $invalid,
                   'pl-11' => $icon || $prefix,
                   'pr-12' => $suffix,
                   'tabular-nums' => in_array($type, ['number', 'time']) || $attributes->has('inputmode'),
               ]) }}>

        @if ($suffix)
            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $suffix }}</span>
        @endif
    </div>
</x-form.field>
