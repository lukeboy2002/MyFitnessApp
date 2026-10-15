{{--
    Afbeelding uploaden met preview en voortgangsbalk (Livewire WithFileUploads).
    <x-form.image-upload label="Afbeelding" wire:model="image"
                         :preview="$image?->temporaryUrl() ?? $exercise?->image_url" remove="removeImage" />
    Voor de profielfoto: shape="round".
--}}
@props(['label' => null, 'name' => null, 'id' => null, 'preview' => null, 'hint' => 'JPG, PNG of WEBP · max 4 MB', 'remove' => null, 'shape' => 'wide'])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id ??= 'f-'.str($name ?? 'image')->replace(['.', '[', ']'], '-')->trim('-');
@endphp

<x-form.field :$label :$name :$id :hint="$preview ? null : $hint" :class="$attributes->get('class')">
    <div x-data="{ uploading: false, progress: 0 }"
         x-on:livewire-upload-start="uploading = true; progress = 0"
         x-on:livewire-upload-finish="uploading = false"
         x-on:livewire-upload-cancel="uploading = false"
         x-on:livewire-upload-error="uploading = false"
         x-on:livewire-upload-progress="progress = $event.detail.progress"
         @class(['flex items-center gap-4' => $shape === 'round'])>

        <label for="{{ $id }}" @class([
            'group relative grid cursor-pointer place-items-center overflow-hidden bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-300 transition hover:bg-brand-50 hover:text-brand-700 has-focus-visible:outline-2 has-focus-visible:outline-accent-400 dark:bg-white/5 dark:text-slate-400 dark:ring-line-dark dark:hover:bg-brand-500/10',
            'aspect-video w-full rounded-2xl border-2 border-dashed border-slate-300 ring-0 dark:border-line-dark' => $shape === 'wide' && ! $preview,
            'aspect-video w-full rounded-2xl' => $shape === 'wide' && $preview,
            'size-24 shrink-0 rounded-full' => $shape === 'round',
        ])>
            @if ($preview)
                <img src="{{ $preview }}" alt="" class="absolute inset-0 size-full object-cover">
                <span class="absolute inset-0 grid place-items-center bg-brand-950/50 font-semibold text-white opacity-0 transition group-hover:opacity-100">
                    <x-icon name="upload" class="size-6" />
                </span>
            @else
                <span class="flex flex-col items-center gap-1.5 p-4 text-center text-sm font-semibold">
                    <x-icon :name="$shape === 'round' ? 'user' : 'image'" class="size-8" />
                    @if ($shape === 'wide') Klik of sleep een afbeelding hierheen @endif
                </span>
            @endif
            <input type="file" id="{{ $id }}" accept="image/*" class="sr-only"
                   @if ($name && ! $attributes->whereStartsWith('wire:model')->first()) name="{{ $name }}" @endif
                   {{ $attributes->except('class') }}>

            <span x-show="uploading" x-cloak class="absolute inset-x-3 bottom-3 h-2 overflow-hidden rounded-full bg-white/70">
                <span class="block h-full rounded-full bg-linear-to-r from-accent-400 to-fire-500 transition-all" :style="`width: ${progress}%`"></span>
            </span>
        </label>

        @if ($shape === 'round' || ($preview && $remove))
            <div @class(['flex flex-wrap gap-2', 'mt-3' => $shape === 'wide'])>
                <x-button variant="secondary" size="sm" icon="upload" type="button" x-on:click="document.getElementById('{{ $id }}').click()">
                    {{ $preview ? 'Wijzigen' : 'Uploaden' }}
                </x-button>
                @if ($preview && $remove)
                    <x-button variant="ghost" size="sm" icon="trash" type="button" wire:click="{{ $remove }}">Verwijderen</x-button>
                @endif
            </div>
        @endif
    </div>
</x-form.field>
