{{-- Superset: koppel deze oefening aan de volgende, of maak het paar weer los (workout-editor en trainingsscherm) --}}
@props(['linked' => false])

<div class="mt-3 flex justify-center">
    <button type="button" {{ $attributes }}
            title="{{ $linked ? __('After a set of the first exercise you go straight to the second; the rest starts after the second.') : __('Link to the next exercise: no rest in between, rest after both.') }}"
            @class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition',
                    'bg-violet-600 text-white hover:bg-violet-700' => $linked,
                    'text-slate-500 ring-1 ring-slate-200 hover:text-violet-700 hover:ring-violet-300 dark:text-slate-400 dark:ring-line-dark dark:hover:text-violet-300' => ! $linked])>
        <x-icon name="link" class="size-4" />
        {{ $linked ? __('Linked · tap to unlink') : __('Superset with next') }}
    </button>
</div>
