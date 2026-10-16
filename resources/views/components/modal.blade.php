{{--
    Herbruikbare modal, gekoppeld aan een Livewire-property:
    <x-modal wire:model="confirmingDeletion" title="Weet je het zeker?"> ... <x-slot:footer>...</x-slot:footer> </x-modal>
    Sluiten vanuit de inhoud: x-on:click="show = false". Staat ook op mobiel in het midden van het scherm.
--}}
@props(['title' => null, 'maxWidth' => 'lg'])

@php
    $model = $attributes->wire('model')->value();
    $width = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'][$maxWidth] ?? 'sm:max-w-lg';
@endphp

<div x-data="{ show: $wire.entangle('{{ $model }}') }"
     x-on:keydown.escape.window="show = false"
     x-effect="document.body.classList.toggle('overflow-hidden', show); if (show) $nextTick(() => $el.querySelector('input, textarea, select')?.focus())"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true">

    <div x-show="show" x-transition.opacity x-on:click="show = false" class="absolute inset-0 bg-brand-950/60 backdrop-blur-sm"></div>

    <div x-show="show"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-4 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 scale-100 opacity-100" x-transition:leave-end="translate-y-4 scale-95 opacity-0"
         class="relative max-h-full w-full {{ $width }} overflow-y-auto rounded-3xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-panel-dark dark:ring-line-dark">
        <div class="px-5 pt-5 sm:px-6 sm:pt-6">
            <div class="mb-4 flex items-start justify-between gap-4">
                @if ($title)
                    <h2 class="font-display text-2xl font-extrabold uppercase leading-none">{{ $title }}</h2>
                @endif
                <button type="button" x-on:click="show = false" aria-label="Sluiten"
                        class="-mr-2 -mt-2 ml-auto grid size-10 place-items-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-ink dark:hover:bg-white/10 dark:hover:text-white">
                    <x-icon name="x" />
                </button>
            </div>
            <div class="pb-5">{{ $slot }}</div>
        </div>

        @isset($footer)
            <div class="flex flex-col-reverse gap-3 rounded-b-3xl border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6 dark:border-line-dark dark:bg-white/[.03]">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
