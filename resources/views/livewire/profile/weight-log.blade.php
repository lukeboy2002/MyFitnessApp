@php
    $n = fn ($v) => \App\Models\WorkoutExercise::number($v);
    $latest = $this->entries->first();
    $first = $this->entries->last();
    $change = $latest && $first && $latest->isNot($first) ? round($latest->weight_kg - $first->weight_kg, 1) : null;
    $toGo = $latest && $target ? round($latest->weight_kg - $target, 1) : null;
@endphp

<x-card title="Gewicht" description="Weeg jezelf op een vast moment, bv. 's ochtends voor het ontbijt.">
    @if ($latest)
        <div class="mb-4 flex flex-wrap items-end gap-x-6 gap-y-2">
            <div>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Laatste weging · {{ $latest->measured_on->translatedFormat('j M') }}</p>
                <p class="text-2xl font-bold tabular-nums">{{ $n($latest->weight_kg) }} <span class="text-base font-semibold text-slate-500">kg</span></p>
            </div>
            @if ($change !== null)
                <p class="text-sm tabular-nums text-slate-600 dark:text-slate-300">
                    <span @class(['font-semibold', 'text-emerald-600 dark:text-emerald-400' => $change < 0, 'text-amber-600 dark:text-amber-400' => $change > 0])>{{ $change > 0 ? '+' : '' }}{{ $n($change) }} kg</span>
                    sinds {{ $first->measured_on->translatedFormat('j M') }}
                </p>
            @endif
            @if ($toGo !== null)
                <p class="text-sm tabular-nums text-slate-600 dark:text-slate-300">
                    @if ($toGo == 0)
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Streefgewicht bereikt</span>
                    @else
                        Nog <span class="font-semibold">{{ $n(abs($toGo)) }} kg</span> {{ $toGo > 0 ? 'eraf' : 'erbij' }} tot {{ $n($target) }} kg
                    @endif
                </p>
            @endif
        </div>

        @if ($this->entries->count() > 1)
            <x-chart.line class="mb-5" unit="kg" :points="$this->entries->reverse()->map(fn ($entry) => ['date' => $entry->measured_on, 'value' => (float) $entry->weight_kg])" />
        @endif
    @endif

    <form wire:submit="save" id="weight-log-form" class="grid grid-cols-2 gap-4">
        <x-form.input label="Gewicht" wire:model="weight" inputmode="decimal" suffix="kg" placeholder="bv. 84,2" />
        <x-form.input label="Vetpercentage" wire:model="bodyFat" inputmode="decimal" suffix="%" placeholder="optioneel" />
        <x-form.input label="Datum" type="date" wire:model="date" :max="today()->format('Y-m-d')" class="col-span-2" />
    </form>

    @if ($this->entries->isNotEmpty())
        <ul class="mt-5 divide-y divide-slate-100 text-sm dark:divide-white/10">
            @foreach ($this->entries->take(5) as $entry)
                <li wire:key="weight-{{ $entry->id }}" class="flex items-center gap-3 py-2">
                    <span class="w-24 shrink-0 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $entry->measured_on->translatedFormat('j M Y') }}</span>
                    <span class="flex-1 font-semibold tabular-nums">
                        {{ $n($entry->weight_kg) }} kg
                        @if ($entry->body_fat !== null)
                            <span class="font-normal text-slate-500 dark:text-slate-400">· {{ $n($entry->body_fat) }}% vet</span>
                        @endif
                    </span>
                    <button type="button" wire:click="delete({{ $entry->id }})" wire:confirm="Deze weging verwijderen?" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" aria-label="Weging verwijderen">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <x-slot:footer>
        <x-action-message on="body-weight-saved">Opgeslagen.</x-action-message>
        <x-button form="weight-log-form" icon="check" loading="save">Weging opslaan</x-button>
    </x-slot:footer>
</x-card>
