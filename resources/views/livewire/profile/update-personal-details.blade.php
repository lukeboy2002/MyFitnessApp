@php
    $n = fn ($v) => \App\Models\WorkoutExercise::number($v);
    $bmi = $user->bmi();
    $bmiLabel = match (true) {
        $bmi === null => null,
        $bmi < 18.5 => __('underweight'),
        $bmi < 25 => __('healthy weight'),
        $bmi < 30 => __('overweight'),
        default => __('obesity'),
    };
    $stats = [
        ['label' => __('Age'), 'value' => $user->age() !== null ? __(':count years', ['count' => $user->age()]) : null, 'hint' => __('from your date of birth')],
        ['label' => __('BMI'), 'value' => $bmi !== null ? $n($bmi) : null, 'hint' => $bmiLabel ?? __('height and weight needed')],
        ['label' => __('Max. heart rate'), 'value' => $user->maxHeartRate() ? $user->maxHeartRate().' bpm' : null, 'hint' => $user->max_heart_rate ? __('your own value') : ($user->maxHeartRate() ? __('estimated from your age') : __('date of birth needed'))],
    ];
    $zones = \App\Support\HeartRateZones::forUser($user);
@endphp

<x-card :title="__('Personal details')" :description="__('Everything is optional. The app uses it for your BMI, heart rate zones and your goal.')">
    <div class="mb-6 grid grid-cols-3 gap-3">
        @foreach ($stats as $stat)
            <div class="rounded-2xl bg-slate-50 px-3 py-3 dark:bg-white/5">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-lg font-bold tabular-nums">{{ $stat['value'] ?? '–' }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($zones)
        <div class="mb-6">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Heart rate zones (you will see them with cardio)') }}</p>
            <p class="mb-2 text-[11px] text-slate-500 dark:text-slate-400">
                @if ($zones->usesReserve())
                    {{ __('Calculated with your resting heart rate (:rest bpm) and max. heart rate (:max bpm), the Karvonen method.', ['rest' => $zones->rest, 'max' => $zones->max]) }}
                @elseif ($user->max_heart_rate)
                    {{ __('Percentage of your own max. heart rate. Also enter your resting heart rate for zones that suit you better.') }}
                @else
                    {{ __('Estimated from your age. Do you know your real max. heart rate or resting heart rate? Enter them below.') }}
                @endif
            </p>
            <ul class="grid gap-1 text-sm">
                @foreach ($zones->all() as $zone)
                    <li class="flex items-center gap-3">
                        <span @class(['size-2.5 shrink-0 rounded-full', 'bg-slate-400' => $zone['number'] === 1, 'bg-sky-500' => $zone['number'] === 2, 'bg-emerald-500' => $zone['number'] === 3, 'bg-amber-500' => $zone['number'] === 4, 'bg-red-500' => $zone['number'] === 5])></span>
                        <span class="w-16 font-semibold">{{ __('Zone :number', ['number' => $zone['number']]) }}</span>
                        <span class="flex-1 text-slate-600 dark:text-slate-300">{{ $zone['name'] }}</span>
                        <span class="tabular-nums text-slate-600 dark:text-slate-300">{{ $zone['from'] }}–{{ $zone['to'] }} bpm</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit="save" id="personal-details-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.date :label="__('Date of birth')" wire:model="birthDate" :max="now()->subDay()->format('Y-m-d')" />
        <x-form.select :label="__('Gender')" wire:model="gender" :options="\App\Enums\Gender::options()" :placeholder="__('I prefer not to say')" />
        <x-form.input :label="__('Height')" wire:model="heightCm" type="number" inputmode="numeric" suffix="cm" :placeholder="__('e.g. :value', ['value' => 180])" />
        <x-form.input :label="__('Target weight')" wire:model="targetWeight" inputmode="decimal" suffix="kg" :placeholder="__('e.g. :value', ['value' => $n(82.5)])" />
        <x-form.select :label="__('Goal')" wire:model="goal" :options="\App\Enums\FitnessGoal::options()" :placeholder="__('Choose your goal…')" />
        <x-form.select :label="__('Experience')" wire:model="experience" :options="\App\Enums\ExperienceLevel::options()" :placeholder="__('Choose…')" />
        <x-form.input :label="__('Max. heart rate')" wire:model="maxHeartRate" type="number" inputmode="numeric" suffix="bpm" :placeholder="$user->estimatedMaxHeartRate() ? __('estimated: :value', ['value' => $user->estimatedMaxHeartRate()]) : __('e.g. :value', ['value' => 175])" :hint="__('The highest your watch ever measured during a hard effort. Empty = estimate.')" />
        <x-form.input :label="__('Resting heart rate')" wire:model="restingHeartRate" type="number" inputmode="numeric" suffix="bpm" :placeholder="__('e.g. :value', ['value' => 60])" :hint="__('Measured in the morning, shown in Apple Health or on your watch.')" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
        <x-button form="personal-details-form" icon="check" loading="save">{{ __('Save') }}</x-button>
    </x-slot:footer>
</x-card>
