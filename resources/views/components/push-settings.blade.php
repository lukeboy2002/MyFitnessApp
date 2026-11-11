{{-- Profiel > Training: pushmeldingen als je rust voorbij is, per apparaat aan/uit (resources/js/push.js) --}}
<x-card title="Melding na je rust" description="Een seintje als je rust voorbij is, ook als je scherm uit staat. Geldt voor dit apparaat.">
    <div x-data="{
            state: 'loading',
            busy: false,
            note: '',
            async init() { this.state = await window.LukeboyPush?.state() ?? 'unsupported' },
            async run(action, done = '') {
                this.busy = true; this.note = '';
                try { const result = await window.LukeboyPush[action](); if (typeof result === 'string') this.state = result; this.note = done }
                catch (e) { this.note = 'Dat lukte niet. Probeer het nog eens.' }
                this.busy = false;
            },
        }" class="flex flex-col gap-4">
        <div class="flex items-start gap-3">
            <span class="grid size-11 shrink-0 place-items-center rounded-xl"
                  :class="state === 'on' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'">
                <x-icon name="clock" />
            </span>
            <p class="min-w-0 flex-1 text-sm text-slate-600 dark:text-slate-300">
                <span x-show="state === 'loading'">Even kijken…</span>
                <span x-show="state === 'on'" x-cloak><strong class="text-ink dark:text-white">Aan op dit apparaat.</strong> Je krijgt een melding als je rust voorbij is. Je telefoon trilt dan volgens je eigen instellingen.</span>
                <span x-show="state === 'off'" x-cloak>Uit. Zet het aan, dan vraagt je telefoon of meldingen mogen.</span>
                <span x-show="state === 'denied'" x-cloak>Je hebt meldingen voor deze app geblokkeerd. Zet ze aan in de instellingen van je telefoon of browser en kom dan terug.</span>
                <span x-show="state === 'install'" x-cloak>Op een iPhone werkt dit alleen vanuit de app op je beginscherm. Tik in Safari op <strong>Deel</strong> en dan op <strong>Zet op beginscherm</strong>, open de app daar en zet het hier aan.</span>
                <span x-show="state === 'unsupported'" x-cloak>Deze browser ondersteunt geen pushmeldingen. Je hoort wel een piep zolang de app open staat.</span>
                <span x-show="state === 'unconfigured'" x-cloak>Pushmeldingen zijn nog niet ingesteld op de server (sleutels in .env).</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2" x-show="['on', 'off'].includes(state)" x-cloak>
            <x-button type="button" icon="check" x-show="state === 'off'" x-on:click="run('enable', 'Meldingen staan aan.')" x-bind:disabled="busy">Meldingen aanzetten</x-button>
            <x-button variant="secondary" type="button" icon="play" x-show="state === 'on'" x-on:click="run('test', 'Testmelding verstuurd.')" x-bind:disabled="busy">Stuur een testmelding</x-button>
            <x-button variant="ghost" type="button" x-show="state === 'on'" x-on:click="run('disable', 'Meldingen staan uit.')" x-bind:disabled="busy">Uitzetten</x-button>
            <span class="text-sm text-slate-500 dark:text-slate-400" x-text="note"></span>
        </div>
    </div>
</x-card>
