<x-card :title="__('Rest time')" :description="__('How long you rest between sets by default. Applies to exercises you add to a workout or training from now on.')">
    <form wire:submit="save" id="rest-time-form">
        <x-form.input :label="__('Rest between sets')" wire:model="seconds" type="number" inputmode="numeric" :suffix="__('sec')"
                      :placeholder="$appDefault" :hint="__('Leave empty = the app default (:seconds sec)', ['seconds' => $appDefault])" />
    </form>

    <x-slot:footer>
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
        <x-button form="rest-time-form" icon="check" loading="save">{{ __('Save') }}</x-button>
    </x-slot:footer>
</x-card>
