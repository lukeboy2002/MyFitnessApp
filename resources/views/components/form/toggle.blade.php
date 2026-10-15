{{-- Schakelaar (aan/uit). <x-form.toggle wire:model.live="weekly_goal_enabled" label="Weekdoel gebruiken" description="..." /> --}}
@props(['label' => null, 'description' => null, 'name' => null, 'id' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= $name ? 'f-'.str($name)->replace(['.', '[', ']'], '-')->trim('-') : null;
@endphp

<label @if ($id) for="{{ $id }}" @endif class="flex cursor-pointer items-center justify-between gap-4">
    <span class="text-sm">
        <span class="font-semibold">{{ $label ?? $slot }}</span>
        @if ($description) <span class="block text-slate-500 dark:text-slate-400">{{ $description }}</span> @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" role="switch" @if ($id) id="{{ $id }}" @endif
               @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
               {{ $attributes->class('peer sr-only') }}>
        <span class="h-7 w-12 rounded-full bg-slate-300 transition peer-checked:bg-brand-700 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent-400 dark:bg-white/15 dark:peer-checked:bg-brand-500"></span>
        <span class="absolute left-1 top-1 size-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
</label>
