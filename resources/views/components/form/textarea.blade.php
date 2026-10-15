{{-- <x-form.textarea label="Omschrijving" wire:model="description" rows="4" hint="Hoe voer je de oefening uit?" /> --}}
@props(['label' => null, 'name' => null, 'id' => null, 'hint' => null, 'required' => false, 'rows' => 4])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
    $invalid = $name && $errors->has($name);
    $c = config('ui.control');
@endphp

<x-form.field :$label :$name :$id :$hint :$required :class="$attributes->get('class')">
    <textarea rows="{{ $rows }}"
              @if ($id) id="{{ $id }}" @endif
              @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
              @if ($required) required @endif
              @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
              {{ $attributes->except('class')->class([$c['base'], 'resize-y', $c['invalid'] => $invalid]) }}>{{ $slot }}</textarea>
</x-form.field>
