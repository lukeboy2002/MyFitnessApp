{{-- Route: Route::view('profile', 'profile.edit')->middleware(['auth', 'verified'])->name('profile.edit'); --}}
@php
    // Tabbladen; een link als /profile#gewicht opent meteen het juiste tabblad
    $tabs = [
        'account' => ['label' => 'Account', 'icon' => 'user'],
        'persoonlijk' => ['label' => 'Persoonlijke gegevens', 'short' => 'Persoonlijk', 'icon' => 'heart'],
        'weekdoel' => ['label' => 'Training', 'icon' => 'dumbbell'],
        'gewicht' => ['label' => 'Gewicht en maten', 'short' => 'Gewicht', 'icon' => 'chart'],
        'beveiliging' => ['label' => 'Beveiliging', 'icon' => 'shield'],
    ];
@endphp

<x-app-layout title="Profiel">
    <x-page-header title="Profiel" :description="auth()->user()->email"/>

    <div x-data="{
            tabs: @js(array_keys($tabs)),
            tab: 'account',
            init() {
                const hash = location.hash.slice(1);
                if (this.tabs.includes(hash)) {
                    this.tab = hash;
                    this.showTab();
                }
            },
            open(tab) {
                this.tab = tab;
                history.replaceState(null, '', '#' + tab);
                this.showTab();
            },
            // Op mobiel het gekozen tabblad in beeld schuiven
            showTab() {
                this.$nextTick(() => this.$refs.tabbar.querySelector('[aria-selected=true]')?.scrollIntoView({ inline: 'center', block: 'nearest' }));
            },
         }">
        {{-- Tabbladen: op mobiel horizontaal scrollen --}}
        <div class="-mx-4 mb-5 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0" role="tablist" aria-label="Profiel" x-ref="tabbar">
            <div class="inline-flex gap-1 rounded-full bg-white p-1 ring-1 ring-slate-200 dark:bg-white/5 dark:ring-line-dark">
                @foreach ($tabs as $key => $item)
                    <button type="button" role="tab" x-on:click="open('{{ $key }}')"
                            x-bind:aria-selected="tab === '{{ $key }}'"
                            x-bind:class="tab === '{{ $key }}'
                                ? 'bg-brand-700 text-white dark:bg-brand-500'
                                : 'text-slate-600 hover:text-ink dark:text-slate-300 dark:hover:text-white'"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition">
                        <x-icon :name="$item['icon']" class="size-4"/>
                        @isset($item['short'])
                            <span class="sm:hidden">{{ $item['short'] }}</span>
                            <span class="hidden sm:inline">{{ $item['label'] }}</span>
                        @else
                            {{ $item['label'] }}
                        @endisset
                    </button>
                @endforeach
            </div>
        </div>

        <div x-show="tab === 'account'" role="tabpanel" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
            <div class="grid gap-5">
                <livewire:profile.update-profile-information/>

                {{-- Eigen gegevens als CSV (ExportController, App\Support\DataExport) --}}
                <x-card title="Je gegevens downloaden" description="Als CSV-bestand, te openen in Excel of Numbers.">
                    <ul class="divide-y divide-slate-100 dark:divide-line-dark">
                        @foreach (\App\Support\DataExport::TYPES as $type => $label)
                            @php($count = \App\Support\DataExport::count(auth()->user(), $type))
                            <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $label }}</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $count }} {{ match ($type) { 'trainingen' => $count === 1 ? 'training' : 'trainingen', 'wegingen' => $count === 1 ? 'weging' : 'wegingen', default => $count === 1 ? 'meting' : 'metingen' } }}{{ $type === 'trainingen' ? ', met alle sets en cardio' : '' }}
                                    </p>
                                </div>
                                @if ($count)
                                    <x-button variant="secondary" size="sm" icon="upload" class="[&>svg]:rotate-180" :href="route('profile.export', $type)" download>Download</x-button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>

            <div class="grid gap-5">
                <livewire:profile.update-profile-photo/>

                <x-card title="Thema" description="Kies light, dark of volg je apparaat.">
                    <x-theme-toggle variant="segmented"/>
                </x-card>
            </div>
        </div>

        <div x-show="tab === 'persoonlijk'" x-cloak role="tabpanel" class="max-w-3xl">
            <livewire:profile.update-personal-details/>
        </div>

        <div x-show="tab === 'weekdoel'" x-cloak role="tabpanel" class="grid max-w-3xl gap-5">
            <livewire:profile.update-weekly-goal/>
            <livewire:profile.update-rest-time/>
        </div>

        <div x-show="tab === 'gewicht'" x-cloak role="tabpanel" class="grid max-w-3xl gap-5">
            <livewire:profile.weight-log/>
            <livewire:profile.body-measurements/>
        </div>

        <div x-show="tab === 'beveiliging'" x-cloak role="tabpanel" class="grid max-w-3xl gap-5">
            <livewire:profile.update-password/>

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <livewire:profile.two-factor-authentication/>
            @endif

            @if (Laravel\Fortify\Features::canManagePasskeys())
                <livewire:profile.passkeys/>
            @endif

            <livewire:profile.delete-user/>
        </div>
    </div>
</x-app-layout>
