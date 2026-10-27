@php
    use App\Models\BodyMeasurement;

    $n = fn ($v) => \App\Models\WorkoutExercise::number($v);
@endphp

<x-card title="Lichaamsmaten" description="Meet met een meetlint, steeds op dezelfde plek. Vul in wat je wilt, de rest mag leeg blijven.">
    @if ($this->entries->isNotEmpty())
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($this->summary as $field => $item)
                <button type="button" wire:click="$set('chartField', '{{ $field }}')"
                        @class(['rounded-2xl px-3 py-3 text-left ring-1 transition',
                                'bg-brand-50 ring-brand-300 dark:bg-brand-500/10 dark:ring-brand-400/40' => $chartField === $field,
                                'bg-slate-50 ring-transparent hover:ring-slate-200 dark:bg-white/5 dark:hover:ring-white/10' => $chartField !== $field])>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums">{{ $item['value'] !== null ? $n($item['value']).' cm' : '–' }}</p>
                    <p class="text-[11px] tabular-nums text-slate-500 dark:text-slate-400">
                        @if ($item['change'] !== null)
                            {{ $item['change'] > 0 ? '+' : '' }}{{ $n($item['change']) }} cm sinds {{ $item['since']->translatedFormat('j M') }}
                        @else
                            &nbsp;
                        @endif
                    </p>
                </button>
            @endforeach
        </div>

        @if ($points->count() > 1)
            <p class="mb-2 text-sm font-semibold">{{ BodyMeasurement::FIELDS[$chartField] }}</p>
            <x-chart.line class="mb-5" unit="cm" :points="$points" />
        @else
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">Na twee metingen van je {{ mb_strtolower(BodyMeasurement::FIELDS[$chartField]) }} zie je hier het verloop. Tik op een maat om die te kiezen.</p>
        @endif
    @endif

    <form wire:submit="save" id="body-measurements-form" class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        @foreach (BodyMeasurement::FIELDS as $field => $label)
            <x-form.input :label="$label" wire:model="values.{{ $field }}" inputmode="decimal" suffix="cm" />
        @endforeach
        <x-form.date label="Datum" id="measurement-date" wire:model="date" :max="today()->format('Y-m-d')" class="col-span-2 sm:col-span-1" />
        @error('values')
            <p class="col-span-full text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </form>

    @if ($this->entries->isNotEmpty())
        <ul class="mt-5 divide-y divide-slate-100 text-sm dark:divide-white/10">
            @foreach ($this->entries->reverse()->take(5) as $entry)
                <li wire:key="measurement-{{ $entry->id }}" class="flex items-center gap-3 py-2">
                    <span class="w-24 shrink-0 whitespace-nowrap text-slate-500 dark:text-slate-400">{{ $entry->measured_on->translatedFormat('j M Y') }}</span>
                    <span class="flex-1 text-slate-600 dark:text-slate-300">
                        {{ collect(BodyMeasurement::FIELDS)->filter(fn ($label, $field) => $entry->{$field} !== null)->map(fn ($label, $field) => mb_strtolower($label).' '.$n($entry->{$field}))->implode(' · ') }}
                    </span>
                    <button type="button" wire:click="delete({{ $entry->id }})" wire:confirm="Deze meting verwijderen?" class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" aria-label="Meting verwijderen">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <x-slot:footer>
        <x-action-message on="body-measurements-saved">Opgeslagen.</x-action-message>
        <x-button form="body-measurements-form" icon="check" loading="save">Maten opslaan</x-button>
    </x-slot:footer>
</x-card>
