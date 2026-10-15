{{--
    <x-form.select label="Lichaamsdeel" wire:model="body_part_id" :options="$bodyParts->pluck('name', 'id')" placeholder="Kies…" />
    Of met eigen <option>-tags in de slot.
--}}
@props(['label' => null, 'name' => null, 'id' => null, 'hint' => null, 'required' => false, 'options' => [], 'placeholder' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
    $invalid = $name && $errors->has($name);
    $c = config('ui.control');
    $selected = old($name);
@endphp

<x-form.field :$label :$name :$id :$hint :$required :class="$attributes->get('class')">
    <div class="relative">
        <select @if ($id) id="{{ $id }}" @endif
                @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
                @if ($required) required @endif
                @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                {{ $attributes->except('class')->class([$c['base'], 'appearance-none pr-10', $c['invalid'] => $invalid]) }}>
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
    </div>
</x-form.field>
