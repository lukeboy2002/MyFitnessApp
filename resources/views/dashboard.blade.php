{{-- Voorbeeld: een gewone pagina binnen de app-layout --}}
<x-app-layout title="Dashboard">
    <x-page-header title="Hoi {{ str(auth()->user()->name)->before(' ') }}"
                   :description="now()->translatedFormat('l j F').' · week '.now()->isoWeek()">
        <x-button variant="fire" icon="play" :href="route('quickstart')">Quickstart</x-button>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-4">
        <x-card
            class="border-0 bg-linear-to-br from-brand-600 via-brand-700 to-brand-900 text-white ring-0 lg:col-span-2 dark:bg-linear-to-br">
            <p class="text-xs font-bold uppercase tracking-widest text-white/80">Trainingstijd deze week</p>
            <p class="mt-1 font-display text-5xl font-extrabold tabular-nums">4u 35m <span
                    class="text-2xl text-white/70">/ 5u</span></p>
        </x-card>
        <x-card title="Trainingen">…</x-card>
        <x-card title="Volume kracht">…</x-card>
    </div>
</x-app-layout>
