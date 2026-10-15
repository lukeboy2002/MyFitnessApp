{{-- <x-avatar :user="auth()->user()" class="size-10" /> --}}
@props(['user'])

@if ($user?->profile_photo_path)
    <img src="{{ Storage::url($user->profile_photo_path) }}" alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => 'size-9 shrink-0 rounded-full object-cover ring-2 ring-white/30']) }}>
@else
    <span {{ $attributes->merge(['class' => 'grid size-9 shrink-0 place-items-center rounded-full bg-accent-400 text-sm font-bold text-brand-900 ring-2 ring-white/30']) }}>
        {{ str($user?->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
    </span>
@endif
