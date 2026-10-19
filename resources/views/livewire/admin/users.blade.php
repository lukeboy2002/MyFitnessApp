<div>
    <x-page-header title="Gebruikers" description="Alle accounts. Als admin beheer je ook de standaardoefeningen, lichaamsdelen, spiergroepen en spieren." />

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @foreach ($this->users as $user)
                <li wire:key="u-{{ $user->id }}" class="flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6">
                    <x-avatar :user="$user" />
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            <span class="truncate">{{ $user->name }}</span>
                            @if ($user->is_admin) <x-badge color="brand" icon="shield">Admin</x-badge> @endif
                            @unless ($user->email_verified_at) <x-badge color="fire">Niet bevestigd</x-badge> @endunless
                        </p>
                        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $user->email }} · sinds {{ $user->created_at->translatedFormat('j M Y') }}</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $user->workouts_count }} workouts · {{ $user->trainings_count }} trainingen
                            @if ($user->last_training_at) · laatste {{ \Illuminate\Support\Carbon::parse($user->last_training_at)->translatedFormat('j M Y') }} @endif
                        </p>
                    </div>
                    @unless ($user->is(auth()->user()))
                        <x-button variant="secondary" size="sm" type="button" icon="shield" wire:click="toggleAdmin({{ $user->id }})"
                                  wire:confirm="{{ $user->is_admin ? 'Adminrechten van '.$user->name.' afnemen?' : $user->name.' admin maken?' }}">
                            {{ $user->is_admin ? 'Admin afnemen' : 'Admin maken' }}
                        </x-button>
                    @endunless
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
