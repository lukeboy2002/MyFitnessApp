{{-- Wachtwoordveld met tonen/verbergen. <x-form.password label="Wachtwoord" name="password" autocomplete="current-password" /> --}}
@props(['label' => null, 'name' => null, 'id' => null, 'hint' => null, 'required' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first() ?? 'password';
    $id ??= 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-');
    $invalid = $errors->has($name);
    $c = config('ui.control');
@endphp

<x-form.field :$label :$name :$id :$hint :$required :class="$attributes->get('class')">
    <div class="relative" x-data="{ show: false }">
        <x-icon name="lock" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
        <input id="{{ $id }}" :type="show ? 'text' : 'password'" type="password"
               @if (! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
               @if ($required) required @endif
               @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->except('class')->class([$c['base'], 'px-11', $c['invalid'] => $invalid]) }}>
        <button type="button" x-on:click="show = ! show" :aria-label="show ? 'Wachtwoord verbergen' : 'Wachtwoord tonen'"
                class="absolute right-1 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-lg text-slate-400 hover:text-ink dark:hover:text-white">
            <x-icon name="eye" class="size-5" x-show="! show" />
            <x-icon name="eye-off" class="size-5" x-show="show" x-cloak />
        </button>
    </div>
</x-form.field>
