// Trainen zonder bereik (trainingsscherm, livewire/sessions/run.blade.php).
//
// Elke set die je invult of afvinkt wordt ook op de telefoon bewaard (localStorage), als eindstand:
// "set 12: 80 kg, 10 reps, afgevinkt". Is er geen verbinding (of mislukt een verzoek), dan houdt deze
// code de klikken bij zelf af van Livewire, laat ze meteen op het scherm zien (met een oranje stip
// = nog niet verstuurd) en start de rusttimer. Zodra er weer verbinding is gaat alles in één keer naar
// Sessions\Run::syncOffline (SyncsOfflineChanges). Knoppen die de server echt nodig hebben worden grijs.
//
// Heropenen zonder bereik: sw.js bewaart de trainingspagina. Wat je na het bewaren van die pagina deed
// (later dan data-rendered) wordt bij het openen opnieuw op het scherm gezet.

const KEY = 'lukeboy-training';
const ACTIONS = /^(toggleSet|toggleWarmup|toggleCardio)\((\d+)\)$/;
const MODELS = /^(values|cardio|splits)\.(\d+)\.(\w+)$/;
const PAGES = 'lukeboy-pages';
const PING = 10000;

const read = () => {
    try {
        return JSON.parse(localStorage.getItem(KEY)) ?? {};
    } catch {
        return {};
    }
};

const modelOf = (el) => [...(el.attributes ?? [])].find((a) => a.name.startsWith('wire:model'))?.value ?? null;

function swap(el, on, onClasses, offClasses) {
    el?.classList.remove(...(on ? offClasses : onClasses));
    el?.classList.add(...(on ? onClasses : offClasses));
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('offlineTraining', (session) => ({
        offline: !navigator.onLine,
        syncing: false,
        synced: false,
        nudge: false,
        entries: {},

        init() {
            const stored = read();
            this.entries = stored.session === session ? (stored.entries ?? {}) : {};
            const rendered = +this.$root.dataset.rendered;

            // Pagina uit de telefoon (sw.js): wat je daarna deed er weer op zetten. Wat nog niet verstuurd is altijd.
            Object.entries(this.entries).forEach(([key, entry]) => {
                if (entry.pending || entry.at > rendered) this.paint(key, entry);
            });

            this.listeners = [
                ['click', (e) => this.onClick(e)],
                ['blur', (e) => this.onInput(e)],
                ['change', (e) => this.onInput(e)],
                ['pointerdown', (e) => this.onDrag(e)],
                ['touchstart', (e) => this.onDrag(e)],
            ];
            this.listeners.forEach(([event, fn]) => document.addEventListener(event, fn, true));

            this.onOnline = () => this.sync();
            this.onOffline = () => this.goOffline();
            this.onHide = () => document.visibilityState === 'hidden' && !this.offline && this.keepPage();
            window.addEventListener('online', this.onOnline);
            window.addEventListener('offline', this.onOffline);
            document.addEventListener('visibilitychange', this.onHide);

            // Mislukt een verzoek (geen bereik), dan wat net gebeurde alsnog op het scherm zetten
            this.stopIntercept = this.$wire.$interceptRequest(({ onSuccess, onFailure }) => {
                const started = Date.now();
                onSuccess(() => this.confirm(started));
                onFailure(() => this.goOffline());
            });

            if (this.offline) this.goOffline();
            else if (this.pendingCount) this.sync();
        },

        destroy() {
            this.listeners?.forEach(([event, fn]) => document.removeEventListener(event, fn, true));
            window.removeEventListener('online', this.onOnline);
            window.removeEventListener('offline', this.onOffline);
            document.removeEventListener('visibilitychange', this.onHide);
            this.stopIntercept?.();
            clearInterval(this.ping);
        },

        get pendingCount() {
            return Object.values(this.entries).filter((entry) => entry.pending).length;
        },

        // --- Wat je doet ---------------------------------------------------------------------

        onClick(e) {
            const el = e.target.closest?.('[wire\\:click]');
            if (!el || !this.$root.contains(el)) return;

            const match = el.getAttribute('wire:click').match(ACTIONS);
            if (!match) {
                if (this.offline) this.block(e);
                return;
            }

            const [, action, id] = match;
            const key = action === 'toggleCardio' ? `cardio:${id}` : `set:${id}`;
            const holder = action === 'toggleCardio' ? el : document.getElementById(`set-row-${id}`);
            if (!holder) return;

            const field = action === 'toggleWarmup' ? 'warmup' : 'done';
            const value = holder.dataset[field] !== '1';
            holder.dataset[field] = value ? '1' : '0';
            this.record(key, { [field]: value });

            if (this.offline) {
                this.stop(e);
                this.paint(key, this.entries[key]);
                if (action === 'toggleSet' && value) this.afterTick(holder);
            } else {
                this.last = { key, holder, action, value, at: Date.now() };
            }
        },

        onInput(e) {
            const el = e.target;
            if (!el?.matches?.('input, select') || !this.$root.contains(el)) return;
            // Een select stuurt bij 'change', een tekstvak bij 'blur'
            if ((e.type === 'change') !== (el.tagName === 'SELECT')) return;

            const match = (modelOf(el) ?? '').match(MODELS);
            if (!match) {
                if (this.offline && modelOf(el)) this.block(e);
                return;
            }

            const [, kind, id, field] = match;
            if (kind === 'values') this.record(`set:${id}`, { [field]: el.value });
            if (kind === 'cardio') this.record(`cardio:${id}`, { fields: { [field]: el.value } });
            if (kind === 'splits') this.record(`splits:${id}`, { fields: { [field]: el.value } });

            if (this.offline) {
                e.stopImmediatePropagation();
                this.paint(`${kind === 'values' ? 'set' : kind}:${id}`, this.entries[`${kind === 'values' ? 'set' : kind}:${id}`]);
            }
        },

        onDrag(e) {
            if (this.offline && e.target.closest?.('[wire\\:sort\\:handle]') && this.$root.contains(e.target)) this.block(e);
        },

        record(key, change) {
            const entry = this.entries[key] ?? {};
            this.entries[key] = {
                ...entry,
                ...change,
                fields: { ...(entry.fields ?? {}), ...(change.fields ?? {}) },
                at: Date.now(),
                pending: true,
            };
            this.save();
        },

        save() {
            try {
                localStorage.setItem(KEY, JSON.stringify({ session, entries: this.entries }));
            } catch {
                // Opslag vol of uitgeschakeld: dan alleen op het scherm
            }
        },

        // Online gelukt: alles van voor dit verzoek staat op de server
        confirm(started) {
            let changed = false;
            Object.values(this.entries).forEach((entry) => {
                if (entry.pending && entry.at <= started) {
                    entry.pending = false;
                    changed = true;
                }
            });
            if (changed) this.save();
        },

        // --- Geen bereik -------------------------------------------------------------------

        goOffline() {
            const first = !this.offline;
            this.offline = true;
            this.grey(true);
            Object.entries(this.entries).forEach(([key, entry]) => entry.pending && this.paint(key, entry));

            // Het afvinken dat net mislukte: toch de rusttimer
            if (first && this.last?.action === 'toggleSet' && this.last.value && Date.now() - this.last.at < 15000) this.afterTick(this.last.holder);
            this.last = null;

            clearInterval(this.ping);
            this.ping = setInterval(() => this.check(), PING);
        },

        async check() {
            try {
                const response = await fetch('/up', { cache: 'no-store' });
                if (response.ok) this.sync();
            } catch {
                // nog steeds geen bereik
            }
        },

        async sync() {
            if (this.syncing) return;
            if (!this.pendingCount) return this.backOnline(false);

            this.syncing = true;
            const started = Date.now();
            try {
                await this.$wire.syncOffline(this.payload());
                this.confirm(started);
                this.backOnline(true);
            } catch {
                this.goOffline();
            } finally {
                this.syncing = false;
            }
        },

        backOnline(saved) {
            clearInterval(this.ping);
            const was = this.offline;
            this.offline = false;
            this.grey(false);
            if (was || saved) {
                this.synced = true;
                setTimeout(() => (this.synced = false), 4000);
            }
            this.keepPage();
        },

        payload() {
            const payload = { sets: {}, cardio: {}, splits: {} };
            Object.entries(this.entries).forEach(([key, entry]) => {
                if (!entry.pending) return;
                const [kind, id] = key.split(':');
                const { at, done, warmup, weight, reps, seconds, fields } = entry;
                if (kind === 'set') payload.sets[id] = { at, done, warmup, weight, reps, seconds };
                if (kind === 'cardio') payload.cardio[id] = { at, done, fields };
                if (kind === 'splits') payload.splits[id] = fields;
            });
            return JSON.parse(JSON.stringify(payload));
        },

        stop(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
        },

        block(e) {
            this.stop(e);
            this.nudge = true;
            clearTimeout(this.nudgeTimer);
            this.nudgeTimer = setTimeout(() => (this.nudge = false), 2500);
        },

        // Knoppen die de server nodig hebben (toevoegen, Later, koppelen, pauze, Afronden…) grijs maken
        grey(on) {
            this.$root.querySelectorAll('[wire\\:click], [wire\\:sort\\:handle]').forEach((el) => {
                if (ACTIONS.test(el.getAttribute('wire:click') ?? '')) return;
                el.style.opacity = on ? '0.35' : '';
            });
        },

        // Na het afvinken zonder bereik: rust, of bij een superset door naar de tweede oefening
        afterTick(row) {
            const section = row.closest('section[data-rest]');
            if (!section) return;

            const partner = section.dataset.partner && document.getElementById(`se-${section.dataset.partner}`);
            if (partner) {
                const next = partner.querySelector('[id^="set-row-"][data-done="0"]');
                window.dispatchEvent(new CustomEvent('superset-next', {
                    detail: { id: +section.dataset.partner, set: next ? +next.id.replace('set-row-', '') : null, name: section.dataset.partnerName, number: next ? +next.dataset.number : null },
                }));
            } else if (+section.dataset.rest > 0) {
                window.dispatchEvent(new CustomEvent('rest-start', { detail: { seconds: +section.dataset.rest } }));
            }
        },

        // De trainingspagina (opnieuw) in de telefoon zetten, zodat hij ook zonder bereik opent
        async keepPage() {
            if (!('caches' in window)) return;
            try {
                const response = await fetch(location.pathname, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
                if (response.ok && !response.redirected) await (await caches.open(PAGES)).put(location.pathname, response);
            } catch {
                // geen bereik: de vorige versie blijft staan
            }
        },

        // --- Op het scherm zetten --------------------------------------------------------------

        paint(key, entry) {
            const [kind, id] = key.split(':');
            const input = (model) => [...this.$root.querySelectorAll('input, select')].find((el) => modelOf(el) === model);

            if (kind === 'set') {
                const row = document.getElementById(`set-row-${id}`);
                if (!row) return;
                ['weight', 'reps', 'seconds'].forEach((field) => {
                    const el = entry[field] !== undefined && input(`values.${id}.${field}`);
                    if (el) el.value = entry[field] ?? '';
                });
                if (entry.done !== undefined) this.paintDone(row, entry.done);
                if (entry.warmup !== undefined) this.paintWarmup(row, entry.warmup);
                row.querySelector('[data-pending]')?.toggleAttribute('hidden', !entry.pending);
            }

            if (kind === 'cardio' || kind === 'splits') {
                Object.entries(entry.fields ?? {}).forEach(([field, value]) => {
                    const el = input(`${kind}.${id}.${field}`);
                    if (el) el.value = value ?? '';
                });
            }

            if (kind === 'cardio') {
                const button = this.$root.querySelector(`[wire\\:click="toggleCardio(${id})"]`);
                if (!button) return;
                if (entry.done !== undefined) {
                    button.dataset.done = entry.done ? '1' : '0';
                    const label = button.querySelector('[data-label]');
                    if (label) label.textContent = entry.done ? label.dataset.on : label.dataset.off;
                }
                button.querySelector('[data-pending]')?.toggleAttribute('hidden', !entry.pending);
            }
        },

        paintDone(row, done) {
            row.dataset.done = done ? '1' : '0';
            swap(row, done, ['bg-emerald-50', 'dark:bg-emerald-500/10'], []);
            swap(row.querySelector('[wire\\:click^="toggleSet"]'), done,
                ['bg-emerald-500', 'text-white', 'hover:bg-emerald-600'],
                ['bg-slate-100', 'text-slate-400', 'hover:bg-slate-200', 'hover:text-ink', 'dark:bg-white/10', 'dark:hover:bg-white/15', 'dark:hover:text-white']);
        },

        paintWarmup(row, warmup) {
            row.dataset.warmup = warmup ? '1' : '0';
            const button = row.querySelector('[wire\\:click^="toggleWarmup"]');
            const label = button?.querySelector('[data-label]');
            if (label) label.textContent = warmup ? label.dataset.on : row.dataset.number;
            swap(button, warmup, ['text-amber-600', 'dark:text-amber-400'], ['text-slate-500', 'dark:text-slate-400']);
        },
    }));
});
