<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#00468c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Lukeboy Fitness">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<title>{{ filled($title ?? null) ? $title.' · ' : '' }}{{ config('app.name', 'Lukeboy Fitness') }}</title>

{{-- Web-app (PWA): icoon op het beginscherm, schermvullend openen. Iconen in public/icons --}}
<link rel="icon" href="{{ asset('icons/favicon-32.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>

<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=barlow:400,500,600,700|barlow-condensed:600,700,800&display=swap">

{{-- Thema direct zetten, vóór de CSS laadt: voorkomt een witte flits in dark mode --}}
<script>
    (() => {
        const mode = localStorage.getItem('theme') ?? 'system';
        const dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Altijd laden, ook op pagina's zonder Livewire-component: Alpine (thema, menu's) komt van Livewire --}}
@livewireStyles
