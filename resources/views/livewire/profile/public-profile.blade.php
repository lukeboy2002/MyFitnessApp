<x-card :title="__('Public profile')" :description="__('Needed to find buddies and to receive workouts.')">
    <x-form.toggle wire:model.live="public" :label="__('Make my profile public')"
                   :description="__('Others can find you by name or username and invite you as a buddy. Your trainings always stay private.')" />
    @unless ($public)
        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ __('Turned off: nobody can find or invite you, and you receive no shared workouts. Existing buddies stay linked.') }}</p>
    @endunless
    <x-action-message on="saved" class="mt-2">{{ __('Saved.') }}</x-action-message>
</x-card>
