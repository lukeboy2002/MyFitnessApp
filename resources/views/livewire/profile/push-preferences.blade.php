<div class="mt-4 grid gap-4 border-t border-slate-200 pt-4 dark:border-line-dark">
    <p class="text-sm font-semibold">{{ __('What do you want to receive?') }} <span class="font-normal text-slate-500 dark:text-slate-400">{{ __('Applies to all your devices.') }}</span></p>
    <x-form.toggle wire:model.live="push_rest" :label="__('Rest is over')" :description="__('A signal during your training when your rest is over.')" />
    <x-form.toggle wire:model.live="push_admin" :label="__('Messages from the admin')" :description="__('News and announcements about the app.')" />
    <x-action-message on="saved">{{ __('Saved.') }}</x-action-message>
</div>
