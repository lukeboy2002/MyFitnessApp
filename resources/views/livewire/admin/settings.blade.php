<div>
    <x-page-header title="Instellingen" description="Instellingen voor de hele app. Alleen admins zien deze pagina." />

    {{-- Site offline --}}
    @php($offline = \App\Support\Offline::isOn())
    <x-card class="mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl',
                          'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => ! $offline,
                          'bg-red-50 text-red-700 dark:bg-red-400/15 dark:text-red-300' => $offline])>
                <x-icon :name="$offline ? 'lock' : 'check'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">De site staat {{ $offline ? 'offline' : 'online' }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $offline ? 'Alleen admins kunnen de app gebruiken; de rest ziet de melding hieronder.' : 'Iedereen kan de app gebruiken. Offline zien alleen admins de app.' }}
                </p>
            </div>
            <x-button :variant="$offline ? 'primary' : 'danger'" type="button" :icon="$offline ? 'check' : 'lock'" wire:click="toggleOffline" loading="toggleOffline"
                      wire:confirm="{{ $offline ? 'Site weer online zetten?' : 'Site offline zetten? Alleen admins kunnen er dan nog in.' }}">
                {{ $offline ? 'Online zetten' : 'Offline zetten' }}
            </x-button>
        </div>
        <x-form.textarea label="Melding voor bezoekers" wire:model="offlineMessage" rows="2" class="mt-4" hint="Wordt opgeslagen als je op de knop drukt" />
    </x-card>

    {{-- Registratie --}}
    @php($open = \App\Support\Registration::isOpen())
    <x-card class="mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl',
                          'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $open,
                          'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300' => ! $open])>
                <x-icon :name="$open ? 'user' : 'lock'" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold">Registratie staat {{ $open ? 'open' : 'dicht' }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $open ? 'Iedereen kan via de inlogpagina een account aanmaken.' : 'Alleen bestaande accounts kunnen inloggen; de knop Registreren is verborgen.' }}
                </p>
            </div>
            <x-button :variant="$open ? 'secondary' : 'primary'" type="button" :icon="$open ? 'lock' : 'user'" wire:click="toggleRegistration"
                      wire:confirm="{{ $open ? 'Registratie dichtzetten?' : 'Registratie openzetten? Dan kan iedereen een account aanmaken.' }}">
                {{ $open ? 'Dichtzetten' : 'Openzetten' }}
            </x-button>
        </div>
    </x-card>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Mededeling --}}
        <x-card title="Mededeling" description="Een bericht bovenaan elke pagina voor alle gebruikers, bv. over onderhoud of iets nieuws.">
            <form wire:submit="saveAnnouncement" id="announcement-form" class="grid gap-4">
                <x-form.textarea label="Tekst" wire:model="announcement" rows="3" hint="Leeg laten = geen mededeling" />
                <x-form.select label="Soort" wire:model="announcementType" :options="['info' => 'Informatie (blauw)', 'warning' => 'Belangrijk (geel)']" />
            </form>

            <x-slot:footer>
                <x-action-message on="announcement-saved">Opgeslagen.</x-action-message>
                <x-button form="announcement-form" icon="check" loading="saveAnnouncement">Opslaan</x-button>
            </x-slot:footer>
        </x-card>

        {{-- Standaardwaarden --}}
        <x-card title="Standaarden" description="Beginwaarden voor nieuwe items. Bestaande workouts en accounts veranderen niet.">
            <form wire:submit="saveDefaults" id="defaults-form" class="grid gap-4">
                <x-form.input label="Rust tussen sets" wire:model="restSeconds" type="number" min="0" max="900" suffix="sec" hint="Voor een oefening die je aan een workout of training toevoegt" />
                <x-form.input label="Weekdoel voor nieuwe accounts" wire:model="weeklyGoalHours" suffix="uur" inputmode="decimal" placeholder="bv. 3 of 4,5" hint="Leeg laten = geen weekdoel" />
            </form>

            <x-slot:footer>
                <x-action-message on="defaults-saved">Opgeslagen.</x-action-message>
                <x-button form="defaults-form" icon="check" loading="saveDefaults">Opslaan</x-button>
            </x-slot:footer>
        </x-card>
    </div>

    {{-- Systeem --}}
    <x-card title="Systeem" description="Hoe de app nu draait." class="mt-5">
        @if (app()->isProduction() && config('app.debug'))
            <x-alert type="danger" :dismissible="false" class="mb-4">Debug staat aan op de server. Zet APP_DEBUG=false in .env en draai php artisan optimize.</x-alert>
        @endif
        @unless (is_link(public_path('storage')))
            <x-alert type="warning" class="mb-4">Plaatjes worden niet getoond: draai php artisan storage:link.</x-alert>
        @endunless

        <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
            @foreach (['Software' => $this->system, 'Aantallen' => $this->totals] as $heading => $rows)
                <div>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $heading }}</h3>
                    <dl class="divide-y divide-slate-100 text-sm dark:divide-line-dark">
                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-4 py-2">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                                <dd class="truncate text-right font-semibold tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            <p class="mr-auto text-sm text-slate-500 dark:text-slate-400">Cache legen helpt als een wijziging niet zichtbaar wordt.</p>
            <x-action-message on="cache-cleared">Cache geleegd.</x-action-message>
            <x-button variant="secondary" type="button" icon="trash" wire:click="clearCache" wire:confirm="Cache legen?" loading="clearCache">Cache legen</x-button>
        </x-slot:footer>
    </x-card>
</div>
