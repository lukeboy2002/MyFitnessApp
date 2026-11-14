<div style="--c: {{ \App\Models\WorkoutSession::QUICKSTART_COLOR }}">
    <x-page-header :title="__('Quickstart')" :description="__('Start right away without a workout. You choose the exercises during the training.')" />

    @if ($running)
        <x-alert type="info" :timeout="0" class="mb-5">
            {{ __('You still have a training running:') }} <a href="{{ route('sessions.run', $running) }}" wire:navigate class="font-semibold underline">{{ $running->name }}</a>. {{ __('Finish that one first.') }}
        </x-alert>
    @endif

    <div class="overflow-hidden rounded-3xl bg-quickstart p-6 text-white shadow-lg sm:p-8">
        <div class="flex items-center gap-3">
            <span class="grid size-12 place-items-center rounded-2xl bg-white/20"><x-icon name="play" class="size-6" /></span>
            <p class="text-sm font-semibold uppercase tracking-wide text-white/80">{{ __('Free training') }}</p>
        </div>
        <h2 class="mt-4 font-display text-[40px] leading-none font-extrabold uppercase">{{ \App\Models\WorkoutSession::QUICKSTART_NAME }}</h2>
        <p class="mt-2 max-w-xl text-white/85">{{ __(\App\Models\WorkoutSession::QUICKSTART_DESCRIPTION) }}</p>

        <div class="mt-6">
            @if ($running)
                <x-button variant="secondary" icon="play" :href="route('sessions.run', $running)" wire:navigate>{{ __('Continue training') }}</x-button>
            @else
                <x-button variant="secondary" icon="play" type="button" wire:click="start" loading="start">{{ __('Start :name', ['name' => \App\Models\WorkoutSession::QUICKSTART_NAME]) }}</x-button>
            @endif
        </div>
    </div>
</div>
