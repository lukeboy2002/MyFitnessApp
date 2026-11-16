<div wire:poll.10s>
    <x-page-header :title="__('Contact (admin)')" :description="__('Questions and requests from users. Your answer appears in their Messages.')" />

    @php($selected = $this->selected)
    <div class="grid items-start gap-5 lg:grid-cols-[2fr_3fr]">
        {{-- Lijst; op mobiel verborgen zolang er een gesprek open is --}}
        <x-card :padding="false" :class="$selected ? 'hidden lg:block' : ''">
            <div class="flex flex-wrap gap-2 px-5 pt-5 sm:px-6">
                @foreach (['open' => __('Open (question)'), 'all' => __('All')] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            @class(['rounded-full px-3 py-1 text-sm font-semibold transition',
                                    'bg-brand-600 text-white' => $filter === $key,
                                    'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300' => $filter !== $key])>
                        {{ $label }} · {{ $this->counts[$key] }}
                    </button>
                @endforeach
            </div>

            @if ($this->requests->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500 sm:px-6 dark:text-slate-400">{{ $filter === 'open' ? __('No open questions. Well done!') : __('No questions yet.') }}</p>
            @else
                <ul class="mt-4 divide-y divide-slate-200 border-t border-slate-200 dark:divide-line-dark dark:border-line-dark">
                    @foreach ($this->requests as $item)
                        @php($isOpen = $item->status === \App\Enums\ContactStatus::Open)
                        <li wire:key="q-{{ $item->id }}">
                            <button type="button" wire:click="choose({{ $item->id }})"
                                    @class(['flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50 sm:px-6 dark:hover:bg-white/[.03]',
                                            'bg-brand-50/60 ring-2 ring-inset ring-brand-500 dark:bg-brand-500/10' => $selected?->id === $item->id])>
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $item->topic->iconClasses() }}">
                                    <x-icon :name="$item->topic->icon()" class="size-4.5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span @class(['block break-words', 'font-bold' => $isOpen, 'font-semibold text-slate-700 dark:text-slate-200' => ! $isOpen])>{{ $item->subject }}</span>
                                    <span class="block truncate text-sm text-slate-600 dark:text-slate-300">
                                        {{ $item->user->name }} · {{ $isOpen ? $item->latestReply?->body : $item->status->label() }}
                                    </span>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $item->updated_at->translatedFormat('j M, H:i') }}</span>
                                </span>
                                @if ($isOpen)
                                    <span class="mt-1 size-2.5 shrink-0 rounded-full bg-fire-500"><span class="sr-only">{{ __('Open (question)') }}</span></span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($selected)
            <x-card wire:key="sel-{{ $selected->id }}" :title="$selected->subject"
                    :description="$selected->topic->label().' · '.$selected->user->name.' · '.$selected->created_at->translatedFormat('j M Y, H:i')">
                <x-slot:actions>
                    <x-badge :color="$selected->status->color()">{{ $selected->status->label() }}</x-badge>
                </x-slot:actions>

                <button type="button" wire:click="back" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-700 lg:hidden dark:text-brand-300">
                    <x-icon name="chevron-left" class="size-4" /> {{ __('All questions') }}
                </button>

                <x-contact-thread :replies="$selected->replies" :mine="false" />

                @if ($selected->topic === \App\Enums\ContactTopic::Exercise)
                    <div class="mt-4">
                        <x-button variant="secondary" size="sm" icon="plus" :href="route('exercises.create', ['name' => $selected->subject, 'standard' => 1])" wire:navigate>
                            {{ __('Create exercise with this name') }}
                        </x-button>
                    </div>
                @endif

                <x-slot:footer>
                    <form wire:submit="reply" class="grid w-full gap-3">
                        <x-form.textarea :label="__('Answer')" wire:model="answer" rows="3" maxlength="2000" required />
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-action-message on="answered" class="me-auto">{{ __('Your answer has been sent to :name.', ['name' => $selected->user->name]) }}</x-action-message>
                            @if ($selected->status === \App\Enums\ContactStatus::Closed)
                                <x-button variant="secondary" icon="plus" type="button" wire:click="reopen">{{ __('Reopen') }}</x-button>
                            @else
                                <x-button variant="secondary" icon="check" type="button" wire:click="close">{{ __('Close without answer') }}</x-button>
                            @endif
                            <x-button icon="mail" type="submit" loading="reply">{{ __('Send answer') }}</x-button>
                        </div>
                    </form>
                </x-slot:footer>
            </x-card>
        @else
            <x-card class="hidden lg:block">
                <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Choose a question on the left.') }}</p>
            </x-card>
        @endif
    </div>
</div>
