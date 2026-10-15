{{-- <x-form.checkbox name="remember" label="Onthoud mij" />   <x-form.checkbox wire:model="is_primary" label="Primair" description="..." /> --}}
@props(['label' => null, 'description' => null, 'name' => null, 'id' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
@endphp

<div class="flex flex-col gap-1">
    <label @if ($id) for="{{ $id }}" @endif class="flex cursor-pointer items-start gap-3">
        <input type="checkbox" @if ($id) id="{{ $id }}" @endif
               @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
               {{ $attributes->class('mt-0.5 size-5 shrink-0 cursor-pointer rounded-md border-slate-300 text-brand-700 accent-brand-700 focus:ring-2 focus:ring-brand-600 focus:ring-offset-0 dark:border-line-dark dark:bg-white/5 dark:accent-brand-400') }}>
        <span class="text-sm">
            <span class="font-semibold">{{ $label ?? $slot }}</span>
            @if ($description) <span class="block text-slate-500 dark:text-slate-400">{{ $description }}</span> @endif
        </span>
    </label>
    @if ($name) <x-form.error :name="$name" /> @endif
</div>
