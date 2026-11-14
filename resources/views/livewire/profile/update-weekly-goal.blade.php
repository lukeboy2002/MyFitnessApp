<x-card :title="__('Weekly goal')" :description="__('How much do you want to train per week? Enter time, number of trainings or both. You will see it on the dashboard.')">
    <form wire:submit="save" id="weekly-goal-form" class="grid gap-5 sm:grid-cols-2">
        <x-form.input :label="__('Training time per week')" wire:model="hours" :suffix="__('hours')" inputmode="decimal" :placeholder="__('e.g. :value', ['value' => \App\Models\WorkoutExercise::number(4.5)])" :hint="__('Leave empty = no time goal')" />
        <x-form.input :label="__('Trainings per week')" wire:model="sessions" type="number" inputmode="numeric" suffix="×" :placeholder="__('e.g. :value', ['value' => 3])" :hint="__('Leave empty = no number')" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
        <x-button form="weekly-goal-form" icon="check" loading="save">{{ __('Save') }}</x-button>
    </x-slot:footer>
</x-card>
