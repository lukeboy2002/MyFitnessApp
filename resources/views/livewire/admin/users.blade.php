<div>
    <x-page-header :title="__('Users')" :description="__('All accounts. As an admin you also manage the standard exercises, body parts, muscle groups and muscles.')" />

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @foreach ($this->users as $user)
                @php($isOpen = $open === $user->id)
                <li wire:key="u-{{ $user->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6">
                    {{-- Aanklikken klapt de details open; een nieuw account is dan gezien --}}
                    <button type="button" wire:click="toggle({{ $user->id }})" aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            class="flex min-w-0 grow basis-full items-center gap-3 rounded-xl text-left sm:basis-0 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-600">
                        <x-avatar :user="$user" />
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-semibold">
                                <span class="truncate">{{ $user->name }}</span>
                                @if ($user->isNew() && ! $user->is(auth()->user())) <x-badge color="fire">{{ __('New') }}</x-badge> @endif
                                @if ($user->is_admin) <x-badge color="brand" icon="shield">Admin</x-badge> @endif
                                @if ($user->isBanned())
                                    <x-badge color="danger" icon="lock">{{ $user->banned_until ? __('Blocked until :date', ['date' => $user->banned_until->translatedFormat('j M Y')]) : __('Blocked') }}</x-badge>
                                @endif
                                @unless ($user->email_verified_at) <x-badge color="fire">{{ __('Not confirmed') }}</x-badge> @endunless
                            </p>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }} · {{ __('since :date', ['date' => $user->created_at->translatedFormat('j M Y')]) }}</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ trans_choice(':count workout|:count workouts', $user->workouts_count) }} · {{ trans_choice(':count training|:count trainings', $user->trainings_count) }}
                                @if ($user->last_training_at) · {{ __('last :date', ['date' => \Illuminate\Support\Carbon::parse($user->last_training_at)->translatedFormat('j M Y')]) }} @endif
                            </p>
                        </div>
                        <x-icon name="chevron-down" @class(['size-5 shrink-0 text-slate-400 transition', 'rotate-180' => $isOpen]) />
                    </button>
                    @unless ($user->is(auth()->user()))
                        <x-button variant="secondary" size="sm" type="button" icon="shield" wire:click="toggleAdmin({{ $user->id }})"
                                  wire:confirm="{{ $user->is_admin ? __('Take away admin rights from :name?', ['name' => $user->name]) : __('Make :name an admin?', ['name' => $user->name]) }}">
                            {{ $user->is_admin ? __('Remove admin') : __('Make admin') }}
                        </x-button>
                    @endunless

                    @if ($isOpen)
                        <dl class="grid w-full gap-x-6 gap-y-2 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:bg-white/5">
                            <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Username') }}</dt><dd class="font-medium">{{ $user->username ?? '–' }}</dd></div>
                            <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Registered') }}</dt><dd class="font-medium">{{ $user->created_at->translatedFormat('j M Y, H:i') }}</dd></div>
                            <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Email confirmed') }}</dt><dd class="font-medium">{{ $user->email_verified_at ? $user->email_verified_at->translatedFormat('j M Y') : __('No') }}</dd></div>
                            <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Language') }}</dt><dd class="font-medium">{{ \App\Support\Locale::available()[$user->locale] ?? '–' }}</dd></div>
                            <div><dt class="text-slate-500 dark:text-slate-400">{{ __('Last training') }}</dt><dd class="font-medium">{{ $user->last_training_at ? \Illuminate\Support\Carbon::parse($user->last_training_at)->translatedFormat('j M Y') : '–' }}</dd></div>
                        </dl>

                        @unless ($user->isBanned())
                            <x-button variant="secondary" size="sm" icon="bell" :href="route('admin.push', ['to' => $user->id])" wire:navigate>{{ __('Send message') }}</x-button>
                        @endunless

                        @unless ($user->is(auth()->user()) || $user->is_admin)
                            <div class="flex w-full flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm dark:bg-white/5">
                                @if ($user->isBanned())
                                    <div class="min-w-0">
                                        <p class="font-semibold text-red-700 dark:text-red-300">
                                            {{ $user->banned_until ? __('Blocked until :date', ['date' => $user->banned_until->translatedFormat('j M Y, H:i')]) : __('Blocked indefinitely') }}
                                        </p>
                                        @if ($user->ban_reason)
                                            <p class="mt-0.5 text-slate-600 dark:text-slate-300">{{ __('Reason: :reason', ['reason' => $user->ban_reason]) }}</p>
                                        @endif
                                    </div>
                                    <x-button variant="secondary" size="sm" type="button" icon="check" wire:click="unban({{ $user->id }})"
                                              wire:confirm="{{ __('Lift the block for :name?', ['name' => $user->name]) }}">
                                        {{ __('Lift block') }}
                                    </x-button>
                                @else
                                    <p class="text-slate-500 dark:text-slate-400">{{ __('Block this account from the site. The data is kept.') }}</p>
                                    <x-button variant="danger" size="sm" type="button" icon="lock" wire:click="startBan({{ $user->id }})">{{ __('Block') }}</x-button>
                                @endif
                            </div>
                        @endunless
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal wire:model="showBan" :title="__('Block account')" maxWidth="md">
        <form wire:submit="ban" id="ban-form" class="grid gap-4">
            @if ($banning)
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ __(':name is logged out immediately on all devices and cannot log in during the block. The trainings and other data are kept.', ['name' => $this->users->firstWhere('id', $banning)?->name]) }}
                </p>
            @endif
            <x-form.select :label="__('How long')" wire:model.live="ban_duration" :options="array_map(fn ($label) => __($label), \App\Livewire\Admin\Users::BAN_DURATIONS)" />
            @if ($ban_duration === 'date')
                <x-form.date :label="__('Until')" wire:model="ban_until" :min="today()->addDay()->format('Y-m-d')" required />
            @endif
            <x-form.textarea :label="__('Reason')" wire:model="ban_reason" rows="3" maxlength="500" :hint="__('Optional. The user sees this when logging in and in the email.')" />
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="$set('showBan', false)">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" form="ban-form" icon="lock" loading="ban">{{ __('Block') }}</x-button>
        </x-slot:footer>
    </x-modal>
</div>
