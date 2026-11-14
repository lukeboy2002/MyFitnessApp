<div>
    <x-page-header :title="__('Badges')" :description="__(':earned of :total levels earned. Everything comes from your completed trainings, including the old ones.', ['earned' => $earned, 'total' => $total])" />

    @if ($recent->isNotEmpty())
        <x-card :title="__('Recently earned')" class="mb-5">
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($recent as $row)
                    <li wire:key="recent-{{ $row['badge']->key }}-{{ $row['level'] }}" class="flex items-center gap-3">
                        <x-medal :badge="$row['badge']" :level="$row['level']" size="sm" />
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ $row['badge']->labelFor($row['level']) }}</span>
                            <span class="block text-sm text-slate-500 dark:text-slate-400">{{ $row['at']->translatedFormat('j M Y') }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($badges as $badge)
            <x-card wire:key="badge-{{ $badge->key }}">
                <div class="flex items-start gap-4">
                    <x-medal :badge="$badge" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ $badge->name }}@if ($badge->isEarned()) · {{ $badge->tierLabel() }}@endif
                        </p>
                        <p @class(['font-semibold', 'text-slate-500 dark:text-slate-400' => ! $badge->isEarned()])>{{ $badge->title() }}</p>
                        @if ($badge->isEarned())
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Earned on :date', ['date' => $badge->earnedAt()->translatedFormat('j M Y')]) }}</p>
                        @endif
                    </div>
                </div>

                @if ($next = $badge->next())
                    <div class="mt-4">
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="truncate text-slate-500 dark:text-slate-400">{{ $badge->isEarned() ? __('Next: :level', ['level' => $badge->labelFor($badge->level())]) : __('Not earned yet') }}</span>
                            <span class="font-semibold tabular-nums">{{ $badge->progress() }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-brand-600 dark:bg-brand-500" style="width: {{ $badge->progress() }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 tabular-nums dark:text-slate-400">{{ $badge->format($badge->value) }} / {{ $badge->format($next) }} {{ $badge->unit }}</p>
                    </div>
                @else
                    <p class="mt-4 flex items-center gap-1.5 text-sm font-semibold text-emerald-700 dark:text-emerald-300"><x-icon name="check" class="size-4" /> {{ __('All levels earned') }}</p>
                @endif

                {{-- Niveaus --}}
                <div class="mt-3 flex gap-1">
                    @foreach ($badge->thresholds as $level => $threshold)
                        <span title="{{ $badge->labelFor($level) }}" @class(['h-1.5 flex-1 rounded-full',
                            'bg-accent-400' => isset($badge->earned[$level]),
                            'bg-slate-200 dark:bg-white/10' => ! isset($badge->earned[$level])])></span>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</div>
