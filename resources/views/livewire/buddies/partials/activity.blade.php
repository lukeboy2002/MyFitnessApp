{{-- Tab Activiteit: gedeelde trainingen van jou en je vrienden (App\Support\FriendActivity) --}}
@php($me = auth()->user())
@php($when = fn ($at) => $at->isToday() ? __('today :time', ['time' => $at->format('H:i')]) : ($at->isYesterday() ? __('yesterday :time', ['time' => $at->format('H:i')]) : $at->translatedFormat('j M, H:i')))

@if ($this->activity->isEmpty())
    <x-card class="max-w-2xl">
        <div class="flex flex-col items-center gap-2 py-4 text-center">
            <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"><x-icon name="thumb" /></span>
            <p class="font-semibold">{{ __('No shared trainings yet') }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('After finishing a training you can share it with your friends. Their shared trainings appear here.') }}</p>
        </div>
    </x-card>
@else
    <ul class="grid max-w-2xl gap-4">
        @foreach ($this->activity as $shared)
            @php($own = $shared->user_id === $me->id)
            @php($summary = $shared->shared_summary ?? [])
            @php($liked = $shared->likes->contains('user_id', $me->id))
            <li wire:key="act-{{ $shared->id }}" style="--c: {{ $shared->color ?? '#1d63c5' }}"
                class="rounded-2xl bg-white bg-[linear-gradient(var(--c),var(--c))] bg-size-[100%_0.375rem] bg-no-repeat pt-1.5 shadow-sm ring-1 ring-slate-200 dark:bg-panel-dark dark:ring-line-dark">
                <div class="flex flex-col gap-3 p-4 sm:p-5">
                    <div class="flex items-center gap-3">
                        <x-avatar :user="$shared->user" class="ring-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $own ? __('You') : $shared->user->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $when($shared->ended_at ?? $shared->started_at) }}</p>
                        </div>
                        @if ($own)
                            <x-badge>{{ __('Shared') }}</x-badge>
                        @endif
                    </div>

                    <p class="font-display text-xl font-bold">{{ $shared->name }}</p>

                    <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-300">
                        <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-4 text-slate-400" /> {{ \App\Support\TrainingStats::hours($summary['seconds'] ?? 0) }}</span>
                        @if ($summary['sets'] ?? 0)
                            <span>{{ trans_choice(':count set|:count sets', $summary['sets']) }}</span>
                        @endif
                        @if (($summary['volume'] ?? 0) > 0)
                            <span>{{ \App\Support\TrainingStats::kilos($summary['volume']) }} kg</span>
                        @endif
                        @if ($summary['distance_m'] ?? 0)
                            <span>{{ \App\Models\WorkoutSession::formatNumber(round($summary['distance_m'] / 1000, 2)) }} km</span>
                        @endif
                    </p>

                    @if ($summary['records'] ?? [])
                        <div x-data="{ all: false }" class="rounded-xl bg-amber-50 p-3 text-sm ring-1 ring-amber-200 dark:bg-amber-400/10 dark:ring-amber-400/30">
                            <p class="flex items-center gap-1.5 font-semibold text-amber-700 dark:text-amber-300">
                                <x-icon name="trophy" class="size-4" /> {{ trans_choice(':count new record!|:count new records!', count($summary['records'])) }}
                            </p>
                            @foreach ($summary['records'] as $line)
                                <p @if ($loop->index >= 3) x-show="all" x-cloak @endif class="text-slate-700 dark:text-slate-200">{{ $line }}</p>
                            @endforeach
                            @if (count($summary['records']) > 3)
                                <button type="button" x-show="! all" x-on:click="all = true" class="mt-1 font-semibold text-amber-700 hover:underline dark:text-amber-300">
                                    {{ __('+:count more', ['count' => count($summary['records']) - 3]) }}
                                </button>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3 dark:border-line-dark">
                        @if ($own)
                            <p class="flex flex-wrap items-center gap-x-1.5 text-sm">
                                @if ($shared->likes->isNotEmpty())
                                    <span class="inline-flex items-center gap-1 font-semibold"><x-icon name="thumb" class="size-4 text-brand-700 dark:text-brand-300" /> {{ trans_choice(':count thumb|:count thumbs', $shared->likes->count()) }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('from :names', ['names' => $shared->likes->pluck('user.name')->join(', ', ' '.__('and').' ')]) }}</span>
                                @else
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('No thumbs yet') }}</span>
                                @endif
                            </p>
                        @else
                            <button type="button" wire:click="like({{ $shared->id }})" aria-pressed="{{ $liked ? 'true' : 'false' }}"
                                    @class(['inline-flex size-10 items-center justify-center rounded-full transition',
                                            'bg-brand-700 text-white dark:bg-brand-500' => $liked,
                                            'bg-white text-brand-700 ring-1 ring-slate-300 hover:bg-slate-50 dark:bg-white/5 dark:text-brand-300 dark:ring-line-dark' => ! $liked])>
                                <x-icon name="thumb" class="size-5" /> <span class="sr-only">{{ $liked ? __('Thumbs up given') : __('Thumbs up') }}</span>
                            </button>
                            @if ($shared->likes->isNotEmpty())
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ trans_choice(':count thumb|:count thumbs', $shared->likes->count()) }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
