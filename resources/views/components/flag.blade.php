{{--
    Kleine vlag (SVG, werkt ook op Windows waar emoji-vlaggen niet bestaan).
    <x-flag code="nl" class="h-4 w-6" />   nl, en (Verenigd Koninkrijk) of de
--}}
@props(['code'])

<svg {{ $attributes->class('shrink-0 overflow-hidden rounded-[3px]') }} viewBox="0 0 60 40" preserveAspectRatio="none" aria-hidden="true">
    @switch($code)
        @case('nl')
            <rect width="60" height="40" fill="#ffffff"/>
            <rect width="60" height="13.34" fill="#ae1c28"/>
            <rect y="26.66" width="60" height="13.34" fill="#21468b"/>
            @break
        @case('de')
            <rect width="60" height="40" fill="#dd0000"/>
            <rect width="60" height="13.34" fill="#000000"/>
            <rect y="26.66" width="60" height="13.34" fill="#ffce00"/>
            @break
        @case('en')
            <rect width="60" height="40" fill="#012169"/>
            <path d="M0 0l60 40M60 0L0 40" stroke="#ffffff" stroke-width="8"/>
            <path d="M0 0l60 40M60 0L0 40" stroke="#c8102e" stroke-width="3"/>
            <path d="M30 0v40M0 20h60" stroke="#ffffff" stroke-width="12"/>
            <path d="M30 0v40M0 20h60" stroke="#c8102e" stroke-width="7"/>
            @break
    @endswitch
</svg>
