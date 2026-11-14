<div>
    <x-page-header :title="__('Users')" :description="__('All accounts. As an admin you also manage the standard exercises, body parts, muscle groups and muscles.')" />

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @foreach ($this->users as $user)
                <li wire:key="u-{{ $user->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6">
                    <x-avatar :user="$user" />
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            <span class="truncate">{{ $user->name }}</span>
                            @if ($user->isNew() && ! $user->is(auth()->user())) <x-badge color="fire">{{ __('New') }}</x-badge> @endif
                            @if ($user->is_admin) <x-badge color="brand" icon="shield">Admin</x-badge> @endif
                            @unless ($user->email_verified_at) <x-badge color="fire">{{ __('Not confirmed') }}</x-badge> @endunless
                        </p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }} · {{ __('since :date', ['date' => $user->created_at->translatedFormat('j M Y')]) }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ trans_choice(':count workout|:count workouts', $user->workouts_count) }} · {{ trans_choice(':count training|:count trainings', $user->trainings_count) }}
                            @if ($user->last_training_at) · {{ __('last :date', ['date' => \Illuminate\Support\Carbon::parse($user->last_training_at)->translatedFormat('j M Y')]) }} @endif
                        </p>
                    </div>
                    @unless ($user->is(auth()->user()))
                        <x-button variant="secondary" size="sm" type="button" icon="shield" wire:click="toggleAdmin({{ $user->id }})"
                                  wire:confirm="{{ $user->is_admin ? __('Take away admin rights from :name?', ['name' => $user->name]) : __('Make :name an admin?', ['name' => $user->name]) }}">
                            {{ $user->is_admin ? __('Remove admin') : __('Make admin') }}
                        </x-button>
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
