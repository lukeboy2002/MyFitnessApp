{{-- Apparaatinstelling: tik om te wijzigen, Enter of wegklikken slaat op, Esc annuleert --}}
<div x-data="{ editing: false, saved: $wire.body }"
     x-on:setting-saved="editing = false; saved = $wire.body">
    <button type="button" x-show="! editing" x-on:click="editing = true; $nextTick(() => $refs.input.focus())"
            class="group flex max-w-full items-center gap-1.5 rounded-lg py-0.5 text-left text-sm text-slate-500 hover:text-ink dark:text-slate-400 dark:hover:text-white">
        <x-icon name="sliders" class="size-4 shrink-0 text-slate-400 group-hover:text-brand-600 dark:group-hover:text-brand-300" />
        @if ($body !== '')
            <span class="min-w-0 break-words font-medium">{{ $body }}</span>
        @else
            <span class="underline decoration-dotted underline-offset-2">{{ __('Add machine setting') }}</span>
        @endif
    </button>

    <form x-show="editing" x-cloak wire:submit="save" class="flex items-center gap-2">
        <x-icon name="sliders" class="size-4 shrink-0 text-brand-600 dark:text-brand-300" />
        <input type="text" x-ref="input" wire:model="body" maxlength="{{ \App\Models\ExerciseSetting::MAX }}"
               placeholder="{{ __('e.g. seat 4, backrest 2') }}" aria-label="{{ __('Machine setting') }}"
               x-on:blur="$wire.save()" x-on:keydown.escape.prevent="$wire.body = saved; editing = false"
               class="min-w-0 flex-1 rounded-lg border-0 bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-500 dark:bg-white/5 dark:ring-line-dark">
    </form>
    @error('body') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
</div>
