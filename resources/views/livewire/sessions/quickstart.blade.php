<div style="--c: {{ \App\Models\WorkoutSession::QUICKSTART_COLOR }}">
    <x-page-header title="Quickstart" description="Direct beginnen zonder workout. Je kiest de oefeningen tijdens de training." />

    @if ($running)
        <x-alert type="info" :timeout="0" class="mb-5">
            Je hebt nog een training lopen: <a href="{{ route('sessions.run', $running) }}" wire:navigate class="font-semibold underline">{{ $running->name }}</a>. Rond die eerst af.
        </x-alert>
    @endif

    <div class="overflow-hidden rounded-3xl bg-quickstart p-6 text-white shadow-lg sm:p-8">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-2xl bg-white/20"><x-icon name="play" class="size-6" /></span>
            <p class="text-sm font-semibold uppercase tracking-wide text-white/80">Vrije training</p>
        </div>
        <h2 class="mt-4 font-display text-[40px] leading-none font-extrabold uppercase">{{ \App\Models\WorkoutSession::QUICKSTART_NAME }}</h2>
        <p class="mt-2 max-w-xl text-white/85">{{ \App\Models\WorkoutSession::QUICKSTART_DESCRIPTION }}</p>

        <div class="mt-6">
            @if ($running)
                <x-button variant="secondary" icon="play" :href="route('sessions.run', $running)" wire:navigate>Verder met training</x-button>
            @else
                <x-button variant="secondary" icon="play" type="button" wire:click="start" loading="start">Start Free Training</x-button>
            @endif
        </div>
    </div>
</div>
