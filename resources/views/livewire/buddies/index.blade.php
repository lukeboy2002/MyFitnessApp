<div>
    <x-page-header :title="__('Buddies')" :description="__('With friends you share workouts, and the trainings you choose to share.')" />

    {{-- Tabs: Activiteit (gedeelde trainingen) en Vrienden (koppelen) --}}
    <div class="mb-5 inline-flex max-w-full overflow-x-auto rounded-full bg-white p-1 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
        @foreach (['activiteit' => __('Activity'), 'vrienden' => __('Buddies')] as $key => $label)
            <button type="button" wire:click="showTab('{{ $key }}')" wire:key="tab-{{ $key }}"
                    @class(['shrink-0 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition',
                            'bg-brand-700 text-white dark:bg-brand-500' => $tab === $key,
                            'text-slate-600 hover:text-ink dark:text-slate-300 dark:hover:text-white' => $tab !== $key])>
                {{ $label }}
                @if ($key === 'vrienden')
                    <span class="ml-0.5 opacity-70">{{ $this->buddies->filter->isAccepted()->count() }}</span>
                    @if ($this->invitations->isNotEmpty())
                        <span class="ml-1 inline-grid size-5 place-items-center rounded-full bg-fire-500 text-xs text-white">{{ $this->invitations->count() }}</span>
                    @endif
                @endif
            </button>
        @endforeach
    </div>

    @if ($message)
        <x-alert type="success" class="mb-5" wire:key="msg-{{ md5($message) }}">{{ $message }}</x-alert>
    @endif

    @if ($tab === 'activiteit')
        @include('livewire.buddies.partials.activity')
    @else
    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">
        <div class="flex flex-col gap-5">
            @if ($this->invitations->isNotEmpty())
                <x-card :title="__('Invitations for you')">
                    <ul class="flex flex-col gap-3">
                        @foreach ($this->invitations as $invitation)
                            <li wire:key="inv-{{ $invitation->id }}" class="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 p-3 ring-1 ring-amber-200 dark:bg-amber-400/10 dark:ring-amber-400/30">
                                <x-avatar :user="$invitation->user" class="shrink-0 ring-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold">{{ $invitation->user->name }}</p>
                                    <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ '@'.$invitation->user->username }}</p>
                                </div>
                                <div class="flex w-full justify-end gap-2 sm:w-auto">
                                    <x-button size="sm" type="button" icon="check" wire:click="accept({{ $invitation->id }})" loading="accept({{ $invitation->id }})">{{ __('Accept') }}</x-button>
                                    <x-button size="sm" variant="ghost" type="button" wire:click="decline({{ $invitation->id }})">{{ __('Decline') }}</x-button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card :title="__('My buddies')" :padding="$this->buddies->isEmpty()">
                @forelse ($this->buddies as $row)
                    @php($other = $row->other(auth()->user()))
                    @if ($loop->first) <ul class="divide-y divide-slate-200 dark:divide-line-dark"> @endif
                    <li wire:key="b-{{ $row->id }}" class="flex items-center gap-3 px-5 py-3 sm:px-6">
                        <x-avatar :user="$other" class="shrink-0 ring-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $other->name }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ '@'.$other->username }} ·
                                {{ $row->isAccepted() ? __('buddy since :date', ['date' => $row->accepted_at->translatedFormat('j M Y')]) : __('waiting for an answer') }}
                                @unless ($other->profile_public) · {{ __('profile not public') }} @endunless
                            </p>
                        </div>
                        @if ($row->isAccepted())
                            <x-button size="sm" variant="ghost" type="button" class="shrink-0 text-red-600 dark:text-red-400" wire:click="remove({{ $row->id }})"
                                      wire:confirm="{{ __('Remove :name as buddy?', ['name' => $other->name]) }}">{{ __('Remove buddy') }}</x-button>
                        @else
                            <x-button size="sm" variant="ghost" type="button" class="shrink-0" wire:click="decline({{ $row->id }})">{{ __('Withdraw') }}</x-button>
                        @endif
                    </li>
                    @if ($loop->last) </ul> @endif
                @empty
                    <div class="flex flex-col items-center gap-2 py-4 text-center">
                        <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"><x-icon name="users" /></span>
                        <p class="font-semibold">{{ __('No buddies yet') }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Invite someone with their username or email address.') }}</p>
                    </div>
                @endforelse
            </x-card>
        </div>

        <div class="flex flex-col gap-5">
            <livewire:profile.public-profile />

            <x-card :title="__('Invite a buddy')">
                @if (auth()->user()->profile_public)
                    <form wire:submit="invite" class="flex flex-col gap-3">
                        <x-form.input wire:model.live.debounce.300ms="login" icon="search" :placeholder="__('Username or email address')" :aria-label="__('Username or email address')" autocomplete="off" autocapitalize="none" />

                        @if (mb_strlen(ltrim(trim($login), '@')) >= \App\Livewire\Buddies\Index::SEARCH_MIN)
                            @if ($this->suggestions->isNotEmpty())
                                <ul class="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200 dark:divide-line-dark dark:ring-line-dark">
                                    @foreach ($this->suggestions as $person)
                                        <li wire:key="s-{{ $person->id }}" class="flex items-center gap-3 px-3 py-2">
                                            <x-avatar :user="$person" class="ring-0" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-semibold">{{ $person->name }}</p>
                                                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ '@'.$person->username }}</p>
                                            </div>
                                            <x-button size="sm" variant="secondary" type="button" icon="plus" class="shrink-0"
                                                      wire:click="inviteUser({{ $person->id }})" loading="inviteUser({{ $person->id }})">{{ __('Invite') }}</x-button>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400" wire:loading.remove wire:target="login">{{ __('No public profile found.') }}</p>
                            @endif
                        @endif

                        <x-button type="submit" variant="secondary" icon="plus" loading="invite" block>{{ __('Invite exact name') }}</x-button>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Type at least 2 letters of a name, username or email address. Only public profiles appear.') }}</p>
                    </form>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Make your profile public first. Then you can find and invite others, and receive workouts.') }}</p>
                @endif
            </x-card>

            <x-card :title="__('Your username')">
                <div x-data="{ copied: false }" class="flex items-center gap-2">
                    <code class="flex-1 truncate rounded-lg bg-slate-100 px-3 py-2 font-semibold dark:bg-white/10">{{ '@'.auth()->user()->username }}</code>
                    <x-button size="sm" variant="secondary" type="button" icon="copy"
                              x-on:click="navigator.clipboard?.writeText(@js(auth()->user()->username)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                    </x-button>
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Give this to a friend so they can invite you.') }}</p>
            </x-card>
        </div>
    </div>
    @endif
</div>
