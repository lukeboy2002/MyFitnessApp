{{-- Knoppenbalk onder een formulier. <x-form.actions><x-button variant="ghost" href="...">Annuleren</x-button><x-button>Opslaan</x-button></x-form.actions> --}}
<div {{ $attributes->merge(['class' => 'flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-end dark:border-line-dark']) }}>
    {{ $slot }}
</div>
