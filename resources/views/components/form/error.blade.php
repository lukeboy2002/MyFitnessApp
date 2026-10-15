{{-- Toont de validatiefout voor één veld. Werkt voor gewone forms en Livewire. --}}
@props(['name', 'id' => null])

@error($name)
    <p @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'flex items-center gap-1.5 text-sm font-medium text-red-600 dark:text-red-400']) }}>
        <x-icon name="alert" class="size-4" /> {{ $message }}
    </p>
@enderror
