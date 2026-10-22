<div>
    <x-page-header title="Badgebeheer" description="De badges en hun niveaus voor alle gebruikers. Wat iemand heeft behaald wordt steeds opnieuw berekend, dus een nieuwe drempel geldt meteen.">
        <x-button icon="plus" type="button" wire:click="create">Badge toevoegen</x-button>
    </x-page-header>

    <x-card :padding="false">
        <ul class="divide-y divide-slate-200 dark:divide-line-dark">
            @foreach ($this->badges as $badge)
                <li wire:key="badge-{{ $badge->id }}" @class(['flex flex-wrap items-center gap-3 px-5 py-4 sm:px-6', 'opacity-60' => ! $badge->is_active])>
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200">
                        <x-icon :name="$badge->icon" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            {{ $badge->name }}
                            @unless ($badge->is_active) <x-badge color="gray">Uit</x-badge> @endunless
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $badge->description() }}</p>
                        <p class="mt-0.5 text-sm tabular-nums">
                            {{ collect($badge->thresholds)->map(fn ($t) => $badge->metric->format($t))->implode(' · ') }}
                            <span class="text-slate-500 dark:text-slate-400">{{ $badge->metric->unit() }}</span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <x-button variant="ghost" size="sm" type="button" wire:click="toggleActive({{ $badge->id }})">{{ $badge->is_active ? 'Uitzetten' : 'Aanzetten' }}</x-button>
                        <x-button variant="secondary" size="sm" type="button" wire:click="edit({{ $badge->id }})">Bewerken</x-button>
                        <x-button variant="ghost" size="sm" type="button" icon="trash" wire:click="confirmDelete({{ $badge->id }})" aria-label="Verwijderen" />
                    </div>
                </li>
            @endforeach
        </ul>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Badge bewerken' : 'Nieuwe badge'" maxWidth="xl">
        <form wire:submit="save" id="badge-form" class="grid gap-4 sm:grid-cols-2">
            <x-form.input label="Naam" wire:model="name" required placeholder="Bijv. Loopbandkoning" />
            <x-form.select label="Icoon" wire:model="icon" :options="\App\Models\Badge::ICONS" />

            <x-form.select label="Wat telt mee" wire:model.live="metric" :options="\App\Enums\BadgeMetric::options()" class="sm:col-span-2" />
            @if (\App\Enums\BadgeMetric::tryFrom($metric)?->needsExercise())
                <x-form.select label="Oefening" wire:model="exercise_id" :options="$this->exercises" placeholder="Kies een oefening…" class="sm:col-span-2" required />
            @endif

            <x-form.input label="Drempels" wire:model.live.debounce.400ms="thresholds" class="sm:col-span-2" required placeholder="1, 10, 25, 50"
                          :suffix="\App\Enums\BadgeMetric::tryFrom($metric)?->unit()"
                          hint="Van laag naar hoog, gescheiden door komma's. Elk getal is een niveau (brons, zilver, goud, platina, diamant…). Decimalen met een punt: 21.1" />
            <x-form.input label="Naam per niveau" wire:model.live.debounce.400ms="label" required placeholder="{n} trainingen" hint="{n} wordt het getal" />
            <x-form.input label="Naam eerste niveau" wire:model.live.debounce.400ms="first_label" placeholder="Bijv. Eerste training" hint="Optioneel" />
            <x-form.input label="Volgorde" type="number" wire:model="sort_order" inputmode="numeric" min="0" />
            <div class="self-end pb-2"><x-form.toggle wire:model="is_active" label="Actief" /></div>

            @if ($this->preview)
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold">Niveaus</p>
                    <ol class="mt-1.5 flex flex-wrap gap-1.5">
                        @foreach ($this->preview as $level => $title)
                            <li class="rounded-lg bg-slate-100 px-2 py-0.5 text-sm dark:bg-white/10">
                                <span class="text-slate-500 dark:text-slate-400">{{ ucfirst(\App\Support\BadgeProgress::TIERS[min($level, 4)]) }}:</span> {{ $title }}
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="ghost" type="button" wire:click="$set('showForm', false)">Annuleren</x-button>
            <x-button form="badge-form" icon="check" loading="save">Opslaan</x-button>
        </x-slot:footer>
    </x-modal>

    <x-confirm-delete :name="$deletingName" title="Badge verwijderen?" />
</div>
