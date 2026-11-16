<div>
    <x-page-header :title="__('Messages')" :description="__('Messages from the admin. You decide what you keep.')">
        <x-button variant="secondary" icon="mail" :href="route('contact')" wire:navigate>{{ __('Contact the admin') }}</x-button>
    </x-page-header>

    @if ($this->messages->isEmpty())
        <x-card>
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <span class="grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-white/10"><x-icon name="bell" /></span>
                <p class="font-semibold">{{ __('No messages') }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Messages from the admin and your training reminders will appear here.') }}</p>
            </div>
        </x-card>
    @else
        @if ($this->messages->whereNull('read_at')->isNotEmpty())
            <div class="mb-3 flex justify-end">
                <x-button variant="ghost" size="sm" type="button" icon="check" wire:click="markAllRead">{{ __('Mark all as read') }}</x-button>
            </div>
        @endif

        <x-card :padding="false">
            <ul class="divide-y divide-slate-200 dark:divide-line-dark">
                @foreach ($this->messages as $message)
                    @php($unread = ! $message->read_at)
                    <li wire:key="m-{{ $message->id }}" id="bericht-{{ $message->id }}"
                        @class(['flex gap-3 px-5 py-4 sm:px-6', 'bg-brand-50/60 dark:bg-brand-500/10' => $unread, 'ring-2 ring-inset ring-brand-500' => $read === $message->id])>
                        <span @class(['mt-2 size-2.5 shrink-0 rounded-full', 'bg-fire-500' => $unread, 'bg-transparent' => ! $unread]) aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p @class(['font-bold' => $unread, 'font-semibold text-slate-700 dark:text-slate-200' => ! $unread])>
                                {{ $message->title }}
                                @if ($unread) <span class="sr-only">({{ __('unread') }})</span> @endif
                            </p>
                            <p class="mt-0.5 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $message->body }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $message->created_at->translatedFormat('j M Y, H:i') }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                @if ($message->url)
                                    <x-button size="sm" variant="secondary" icon="chevron-right" :href="$message->url" wire:navigate>{{ __('Open (message)') }}</x-button>
                                @endif
                                @if ($unread)
                                    <x-button size="sm" variant="ghost" type="button" icon="check" wire:click="markRead({{ $message->id }})">{{ __('Read') }}</x-button>
                                @else
                                    <x-button size="sm" variant="ghost" type="button" icon="eye-off" wire:click="markUnread({{ $message->id }})">{{ __('Unread') }}</x-button>
                                @endif
                                <x-button size="sm" variant="ghost" type="button" icon="trash" wire:click="delete({{ $message->id }})"
                                          wire:confirm="{{ __('Delete this message?') }}">{{ __('Delete') }}</x-button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
