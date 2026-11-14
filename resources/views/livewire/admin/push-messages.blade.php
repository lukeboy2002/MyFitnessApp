<div>
    <x-page-header :title="__('Push notifications')" :description="__('Send a push notification to everyone or to one user. Only users who turned on push notifications and messages from the admin in their profile receive it.')" />

    @unless ($configured)
        <x-alert type="warning" :dismissible="false" class="mb-5">{{ __('Push notifications are not set up on this server (VAPID keys missing in .env).') }}</x-alert>
    @endunless

    <div class="grid items-start gap-5 lg:grid-cols-2">
        <x-card :title="__('New push notification')">
            <form wire:submit="send" wire:confirm="{{ __('Send this push notification now?') }}" id="push-form" class="grid gap-4">
                <x-form.radio-cards :label="__('To')" wire:model.live="audience" :options="[
                    'everyone' => ['label' => __('Everyone'), 'icon' => 'user', 'description' => trans_choice(':count user|:count users', $this->recipients->count())],
                    'user' => ['label' => __('One user'), 'icon' => 'search', 'description' => __('Choose from the list')],
                ]" />

                @if ($audience === 'user')
                    <x-form.select :label="__('User')" wire:model.live="recipient_id" :options="$this->userOptions" :placeholder="__('Choose a user…')" required
                                   :hint="__('Only users who turned on push notifications and messages from the admin in their profile can receive it.')" />
                @endif

                <x-form.input :label="__('Title')" wire:model="title" maxlength="80" required :placeholder="__('e.g. :example', ['example' => __('New badges!')])" />
                <x-form.textarea :label="__('Text')" wire:model="body" rows="3" maxlength="300" required />
                <x-form.select :label="__('Opens when tapped')" wire:model="link" :options="array_map(fn ($label) => __($label), \App\Models\PushBroadcast::LINKS)" :placeholder="__('Dashboard (default)')" />
            </form>

            <x-slot:footer>
                <p class="mr-auto text-sm text-slate-500 dark:text-slate-400">{{ trans_choice('Reaches :count user|Reaches :count users', $this->reach) }}</p>
                <x-button form="push-form" icon="bell" loading="send" :disabled="! $configured || $this->reach === 0">{{ __('Send') }}</x-button>
            </x-slot:footer>
        </x-card>

        <x-card :title="__('Sent')" :padding="false">
            @if ($this->history->isEmpty())
                <p class="px-5 pb-5 text-sm text-slate-500 sm:px-6 dark:text-slate-400">{{ __('No push notifications sent yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-line-dark">
                    @foreach ($this->history as $broadcast)
                        <li wire:key="b-{{ $broadcast->id }}" class="px-5 py-4 sm:px-6">
                            <p class="font-semibold">{{ $broadcast->title }}</p>
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $broadcast->body }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $broadcast->to_everyone ? __('Everyone') : ($broadcast->recipient?->name ?? $broadcast->recipient_name ?? '–') }}
                                · {{ $broadcast->created_at->translatedFormat('j M Y, H:i') }}
                                · @if ($broadcast->sent_at)
                                    {{ trans_choice(':count device reached|:count devices reached', $broadcast->delivered_count) }}
                                @else
                                    {{ __('Being sent…') }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
