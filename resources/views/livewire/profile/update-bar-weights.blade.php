<x-card :title="__('Plate calculator')" :description="__('The weight of the bars in your gym. During training you see which plates go on each side.')">
    <form wire:submit="save" id="bar-weights-form" class="grid gap-4 sm:grid-cols-3">
        @foreach (['bar' => __('Straight bar'), 'ez_bar' => __('EZ bar'), 'trap_bar' => __('Trap bar')] as $type => $label)
            <x-form.input :label="$label" wire:model="weights.{{ $type }}" inputmode="decimal" suffix="kg"
                          :placeholder="$defaults[$type]" :hint="__('Empty = :kg kg', ['kg' => $defaults[$type]])" />
        @endforeach
    </form>

    <x-slot:footer>
        <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
        <x-button form="bar-weights-form" icon="check" loading="save">{{ __('Save') }}</x-button>
    </x-slot:footer>
</x-card>
