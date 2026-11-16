<div>
    <x-page-header :title="__('Contact')" :description="__('Missing an exercise, found a problem or have a question? Let the admin know.')" />

    <div class="grid items-start gap-5 lg:grid-cols-[3fr_2fr]">
        <x-card :title="__('New question')">
            <form wire:submit="send" class="grid gap-4">
                <x-form.radio-cards :label="__('What is it about?')" wire:model.live="topic" :options="$topics" />
                <x-form.input wire:model="subject" maxlength="120" required
                              :label="$topic === 'exercise' ? __('Name of the exercise') : __('Subject')"
                              :hint="$topic === 'exercise' ? __('As it is on the machine or in your schedule.') : null"
                              :placeholder="$topic === 'exercise' ? __('e.g. :example', ['example' => 'Hip Thrust (Machine)']) : null" />
                <x-form.textarea :label="__('Message')" wire:model="body" rows="4" maxlength="2000" required
                                 :placeholder="$topic === 'exercise' ? __('Which muscles, which machine, anything else that helps.') : null" />
                <div class="flex justify-end">
                    <x-button icon="mail" type="submit" loading="send">{{ __('Send') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('My questions')" :padding="$this->requests->isEmpty()">
            @if ($this->requests->isEmpty())
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('You have not asked anything yet. Your questions and the answers will appear here.') }}</p>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-line-dark">
                    @foreach ($this->requests as $request)
                        @php($unread = $request->hasUnreadAnswer())
                        <li wire:key="c-{{ $request->id }}">
                            <a href="{{ route('contact.show', $request) }}" wire:navigate
                               @class(['flex items-start gap-3 px-5 py-4 transition hover:bg-slate-50 sm:px-6 dark:hover:bg-white/[.03]', 'bg-brand-50/60 dark:bg-brand-500/10' => $unread])>
                                <span @class(['mt-2 size-2.5 shrink-0 rounded-full', 'bg-fire-500' => $unread, 'bg-transparent' => ! $unread]) aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span @class(['min-w-0 break-words', 'font-bold' => $unread, 'font-semibold' => ! $unread])>{{ $request->topic->label() }}: {{ $request->subject }}</span>
                                        <x-badge :color="$request->status->color()">{{ $request->status->label() }}</x-badge>
                                        @if ($unread) <span class="sr-only">({{ __('new answer') }})</span> @endif
                                    </span>
                                    @if ($request->latestReply)
                                        <span class="mt-0.5 block truncate text-sm text-slate-600 dark:text-slate-300">
                                            {{ $request->latestReply->is_admin ? __('Admin').': ' : '' }}{{ $request->latestReply->body }}
                                        </span>
                                    @endif
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $request->updated_at->translatedFormat('j M Y, H:i') }}</span>
                                </span>
                                <x-icon name="chevron-right" class="mt-1 size-5 shrink-0 text-slate-400" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
