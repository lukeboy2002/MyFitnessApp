{{-- Berichten van een contactgesprek als chat. $mine: true = berichten van de gebruiker rechts (gebruikerskant), false = admin rechts --}}
@props(['replies', 'mine' => true])

<ol class="grid gap-4">
    @foreach ($replies as $reply)
        @php($right = $mine ? ! $reply->is_admin : $reply->is_admin)
        <li wire:key="r-{{ $reply->id }}"
            @class(['max-w-[85%] rounded-2xl px-4 py-3',
                    'ml-auto rounded-br-md bg-brand-600 text-white' => $right,
                    'mr-auto rounded-bl-md bg-slate-100 dark:bg-white/10' => ! $right])>
            <p @class(['text-xs font-semibold', 'text-white/75' => $right, 'text-slate-500 dark:text-slate-400' => ! $right])>
                {{ $reply->is_admin ? ($mine ? __('Admin') : ($reply->author?->name ?? __('Admin'))) : ($mine ? __('You') : ($reply->author?->name ?? '–')) }}
                · {{ $reply->created_at->translatedFormat('j M, H:i') }}
            </p>
            <p class="mt-1 whitespace-pre-line break-words">{{ $reply->body }}</p>
        </li>
    @endforeach
</ol>
