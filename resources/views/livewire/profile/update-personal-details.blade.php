@php
    $n = fn ($v) => \App\Models\WorkoutExercise::number($v);
    $bmi = $user->bmi();
    $bmiLabel = match (true) {
        $bmi === null => null,
        $bmi < 18.5 => 'ondergewicht',
        $bmi < 25 => 'gezond gewicht',
        $bmi < 30 => 'overgewicht',
        default => 'obesitas',
    };
    $stats = [
        ['label' => 'Leeftijd', 'value' => $user->age() !== null ? $user->age().' jaar' : null, 'hint' => 'uit je geboortedatum'],
        ['label' => 'BMI', 'value' => $bmi !== null ? $n($bmi) : null, 'hint' => $bmiLabel ?? 'lengte en gewicht nodig'],
        ['label' => 'Max. hartslag', 'value' => $user->maxHeartRate() ? $user->maxHeartRate().' bpm' : null, 'hint' => 'geschat uit je leeftijd'],
    ];
@endphp

<x-card title="Persoonlijke gegevens" description="Alles is optioneel. De app gebruikt het voor je BMI, hartslagzones en je doel.">
    <div class="mb-6 grid grid-cols-3 gap-3">
        @foreach ($stats as $stat)
            <div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-white/5">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-lg font-bold tabular-nums">{{ $stat['value'] ?? '–' }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($zones = \App\Support\HeartRateZones::forUser($user))
        <div class="mb-6">
            <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Hartslagzones (zie je terug bij cardio)</p>
            <ul class="grid gap-1 text-sm">
                @foreach ($zones->all() as $zone)
                    <li class="flex items-center gap-3">
                        <span @class(['size-2.5 shrink-0 rounded-full', 'bg-slate-400' => $zone['number'] === 1, 'bg-sky-500' => $zone['number'] === 2, 'bg-emerald-500' => $zone['number'] === 3, 'bg-amber-500' => $zone['number'] === 4, 'bg-red-500' => $zone['number'] === 5])></span>
                        <span class="w-16 font-semibold">Zone {{ $zone['number'] }}</span>
                        <span class="flex-1 text-slate-600 dark:text-slate-300">{{ $zone['name'] }}</span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ $zone['from'] }}–{{ $zone['to'] }} bpm</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" id="personal-details-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.date label="Geboortedatum" wire:model="birthDate" :max="now()->subDay()->format('Y-m-d')" />
        <x-form.select label="Geslacht" wire:model="gender" :options="\App\Enums\Gender::options()" placeholder="Zeg ik liever niet" />
        <x-form.input label="Lengte" wire:model="heightCm" type="number" inputmode="numeric" suffix="cm" placeholder="bv. 180" />
        <x-form.input label="Streefgewicht" wire:model="targetWeight" inputmode="decimal" suffix="kg" placeholder="bv. 82,5" />
        <x-form.select label="Doel" wire:model="goal" :options="\App\Enums\FitnessGoal::options()" placeholder="Kies je doel…" />
        <x-form.select label="Ervaring" wire:model="experience" :options="\App\Enums\ExperienceLevel::options()" placeholder="Kies…" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">Opgeslagen.</x-action-message>
        <x-button form="personal-details-form" icon="check" loading="save">Opslaan</x-button>
    </x-slot:footer>
</x-card>
