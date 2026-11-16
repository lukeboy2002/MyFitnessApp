<div wire:poll.10s>
    <x-page-header :title="$request->subject" :description="$request->topic->label().' · '.__('started :date', ['date' => $request->created_at->translatedFormat('j M Y')])">
        <x-button variant="secondary" icon="chevron-left" :href="route('contact')" wire:navigate>{{ __('My questions') }}</x-button>
    </x-page-header>

    <x-card class="max-w-3xl">
        <div class="mb-4"><x-badge :color="$request->status->color()">{{ $request->status->label() }}</x-badge></div>

        <x-contact-thread :replies="$replies" />

        @if ($request->status === \App\Enums\ContactStatus::Open)
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ __('The admin will answer as soon as possible. You will get the answer in Messages.') }}</p>
        @endif

        <x-slot:footer>
            <form wire:submit="send" class="grid w-full gap-3">
                <x-form.textarea :label="$request->status === \App\Enums\ContactStatus::Closed ? __('Respond (opens the question again)') : __('Respond')"
                                 wire:model="body" rows="2" maxlength="2000" :placeholder="__('Type your reaction…')" />
                <div class="flex justify-end"><x-button icon="mail" type="submit" loading="send">{{ __('Send') }}</x-button></div>
            </form>
        </x-slot:footer>
    </x-card>
</div>
