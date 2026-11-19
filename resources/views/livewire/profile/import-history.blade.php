{{-- Profiel > Account: importeren uit Hevy, Strong of FitNotes (App\Livewire\Profile\ImportHistory) --}}
<x-card :title="__('Import from another app')" :description="__('Bring your training history from Hevy, Strong or FitNotes.')">
    @if ($stored && $this->workoutFile)
        {{-- Stap 2: controleren en oefeningen koppelen --}}
        @php
            $preview = $this->preview;
            $chosen = $this->chosen;
            $names = collect($map)->keys();
            $auto = $names->filter(fn ($name) => in_array($name, $sure, true) && $map[$name]);
            $todo = $names->diff($auto)->sortBy(fn ($name) => match (true) { $map[$name] === null => 0, $map[$name] > 0 => 1, default => 2 })->values();
        @endphp
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold">{{ __('File from :app', ['app' => $this->workoutFile->appLabel()]) }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $filename }}</p>
                </div>
                <x-badge color="success">{{ __('Recognised') }}</x-badge>
            </div>

            <div class="grid grid-cols-3 gap-2 text-center">
                @foreach ([[$preview['trainings'], trans_choice('training|trainings', $preview['trainings'])], [\App\Support\TrainingStats::kilos($preview['sets']), trans_choice('set|sets', $preview['sets'])], [count($preview['exercises']), trans_choice('exercise|exercises', count($preview['exercises']))]] as [$value, $label])
                    <div class="rounded-xl bg-slate-50 p-2 dark:bg-white/5">
                        <p class="font-display text-xl font-bold">{{ $value }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    </div>
                @endforeach
            </div>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ $preview['from']?->translatedFormat('j M Y') }} – {{ $preview['until']?->translatedFormat('j M Y') }}
                @if ($preview['existing'])
                    · {{ trans_choice(':count training is already in your history|:count trainings are already in your history', $preview['existing']) }}
                @endif
            </p>

            @if ($this->workoutFile->needsUnit())
                <div>
                    <p class="mb-1.5 text-sm font-semibold">{{ __('Units in this file') }}</p>
                    <div class="inline-flex rounded-full bg-white p-1 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
                        @foreach ([0 => __('kg and km'), 1 => __('lbs and miles')] as $value => $label)
                            <button type="button" wire:click="$set('imperial', {{ $value ? 'true' : 'false' }})"
                                    @class(['rounded-full px-4 py-1.5 text-sm font-semibold transition',
                                            'bg-brand-700 text-white dark:bg-brand-500' => $imperial == $value,
                                            'text-slate-600 dark:text-slate-300' => $imperial != $value])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($auto->isNotEmpty())
                <div x-data="{ open: false }" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300">
                    <p class="flex flex-wrap items-center gap-x-2">
                        <x-icon name="check" class="size-4" />
                        {{ trans_choice(':count exercise linked automatically|:count exercises linked automatically', $auto->count()) }}
                        <button type="button" x-on:click="open = ! open" class="font-semibold underline" x-text="open ? @js(__('Hide')) : @js(__('View'))"></button>
                    </p>
                    <ul x-show="open" x-cloak class="mt-2 space-y-1">
                        @foreach ($auto as $name)
                            <li wire:key="auto-{{ md5($name) }}" class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate">{{ $name }} → <strong>{{ $chosen[$map[$name]] ?? '' }}</strong></span>
                                <button type="button" wire:click="choose(@js($name))" class="shrink-0 font-semibold underline">{{ __('Change') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($todo->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">{{ trans_choice('Choose :count exercise|Choose :count exercises', $todo->count()) }}</p>
                    @foreach ($todo as $name)
                        @php($id = $map[$name])
                        <div wire:key="todo-{{ md5($name) }}" class="rounded-xl p-3 ring-1 ring-slate-200 dark:ring-line-dark">
                            <p class="font-semibold">{{ $name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice('Done :count time|Done :count times', $preview['exercises'][$name]['trainings'] ?? 0) }}</p>
                            <button type="button" wire:click="choose(@js($name))"
                                    class="mt-2 flex w-full items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-left text-sm hover:bg-slate-100 dark:bg-white/5 dark:hover:bg-white/10">
                                <span @class(['truncate', 'text-slate-500 dark:text-slate-400' => ! $id])>
                                    {{ $id ? $chosen[$id] ?? '' : ($id === 0 ? __('Skipped') : __('Choose an exercise…')) }}
                                </span>
                                <x-icon name="chevron-down" class="size-4 shrink-0 text-slate-400" />
                            </button>
                            @if ($id)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Suggestion. Not right? Choose another one.') }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
                                <button type="button" wire:click="createOwn(@js($name))" class="text-brand-700 hover:underline dark:text-brand-300">+ {{ __('Create own exercise') }}</button>
                                @if ($id !== 0)
                                    <button type="button" wire:click="skip(@js($name))" class="text-slate-500 hover:underline dark:text-slate-400">{{ __('Skip') }}</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Exercises you do not choose are skipped.') }}</p>
                </div>
            @endif

            <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
                <x-button variant="ghost" type="button" wire:click="cancel">{{ __('Cancel') }}</x-button>
                <x-button type="button" icon="upload" class="[&>svg]:rotate-180" wire:click="import" loading="import" :disabled="! $preview['new']">
                    {{ trans_choice('Import :count training|Import :count trainings', $preview['new']) }}
                </x-button>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @if ($result)
                {{-- Stap 3: klaar --}}
                <div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-400/10 dark:text-emerald-200">
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="check" class="size-5" /> {{ __('Import complete') }}</p>
                    <p class="mt-1">{{ trans_choice(':count training added to your history.|:count trainings added to your history.', $result['created']) }}</p>
                    @if ($result['skipped'])
                        <p class="text-emerald-800/70 dark:text-emerald-200/70">{{ trans_choice(':count skipped: already in your history or without a linked exercise.|:count skipped: already in your history or without linked exercises.', $result['skipped']) }}</p>
                    @endif
                    @if ($result['badges'])
                        <p class="mt-1">🏆 {{ trans_choice(':count new badge level earned|:count new badge levels earned', $result['badges']) }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-button size="sm" :href="route('progress.index')" icon="chart">{{ __('To Progress') }}</x-button>
                    </div>
                </div>
            @endif

            {{-- Stap 1: bestand kiezen --}}
            <div class="flex flex-wrap gap-2 text-sm">
                @foreach (\App\Support\Import\WorkoutFile::APPS as $label)
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200">{{ $label }}</span>
                @endforeach
            </div>

            <label class="block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 p-6 text-center transition hover:border-brand-500 hover:bg-slate-50 dark:border-line-dark dark:hover:bg-white/5">
                <input type="file" wire:model="file" accept=".csv,text/csv,text/plain" class="sr-only">
                <span wire:loading.remove wire:target="file">
                    <x-icon name="upload" class="mx-auto size-7 text-slate-400" />
                    <span class="mt-2 block font-semibold text-brand-700 dark:text-brand-300">{{ __('Choose your CSV file') }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('The app recognises which app it comes from') }}</span>
                </span>
                <span wire:loading wire:target="file" class="text-sm font-semibold text-slate-500">{{ __('Reading file…') }}</span>
            </label>
            @error('file') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <details class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-white/5 dark:text-slate-300">
                <summary class="cursor-pointer font-semibold text-ink dark:text-white">{{ __('How do I get that file?') }}</summary>
                <p class="mt-2"><strong>Hevy:</strong> {{ __('Profile › Settings › Export & Import Data › Export workouts') }}</p>
                <p><strong>Strong:</strong> {{ __('Settings › Export Strong Data (Android: Export Data)') }}</p>
                <p><strong>FitNotes:</strong> {{ __('Settings › Data › Spreadsheet Export › Workout Data') }}</p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Trainings that are already in your history are skipped. Imported trainings are never shared with friends.') }}</p>
            </details>

            @if ($this->imports->isNotEmpty())
                <div>
                    <p class="mb-1 text-sm font-semibold">{{ __('Earlier imports') }}</p>
                    <ul class="divide-y divide-slate-100 text-sm dark:divide-line-dark">
                        @foreach ($this->imports as $import)
                            <li wire:key="import-{{ $import->id }}" class="flex items-center gap-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $import->appLabel() }} · {{ $import->created_at->translatedFormat('j M Y') }}</p>
                                    <p class="truncate text-slate-500 dark:text-slate-400">{{ trans_choice(':count training|:count trainings', $import->sessions_count) }} · {{ $import->filename }}</p>
                                </div>
                                <x-button variant="secondary" size="sm" type="button" wire:click="confirmUndo({{ $import->id }})">{{ __('Undo') }}</x-button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <x-modal wire:model="showPicker" :title="__('Choose an exercise')" maxWidth="2xl">
        @if ($showPicker)
            @if ($pickingFor)
                <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{{ __('For :name', ['name' => $pickingFor]) }}</p>
            @endif
            <x-exercise-picker :exercises="$this->pickerExercises" :groups="$this->muscleGroups" :equipment="$this->pickerEquipmentOptions" :type="$pickerType" :own="$pickerOwn" :own-count="$this->pickerOwnCount" action="pick" />
        @endif
        <x-slot:footer>
            <x-button variant="ghost" type="button" x-on:click="show = false">{{ __('Cancel') }}</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="confirmingUndo" :title="__('Undo import?')" maxWidth="md">
        @php($undoing = $undoingId ? $this->imports->firstWhere('id', $undoingId) : null)
        @if ($undoing)
            <p class="text-sm">{{ trans_choice('The :count training from this import will be removed from your history.|The :count trainings from this import will be removed from your history.', $undoing->sessions_count) }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Your own trainings stay. You can import the file again later.') }}</p>
        @endif
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="$set('confirmingUndo', false)">{{ __('Cancel') }}</x-button>
            <x-button variant="danger" type="button" wire:click="undo" loading="undo">{{ __('Undo import') }}</x-button>
        </x-slot:footer>
    </x-modal>
</x-card>
