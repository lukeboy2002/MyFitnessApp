{{-- Getoond aan iedereen behalve admins als de site offline staat (BlockWhenOffline) --}}
<x-guest-layout title="Even offline">
    <div class="flex flex-col gap-5">
        <p class="text-slate-600 dark:text-slate-300">{!! nl2br(e($message)) !!}</p>

        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button variant="secondary" icon="logout" block>Uitloggen</x-button>
            </form>
        @else
            <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">Beheerder? Inloggen</a>
        @endauth
    </div>
</x-guest-layout>
