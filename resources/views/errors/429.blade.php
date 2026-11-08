@extends('errors.layout')

@php
    // Hoe lang wachten? Staat in de Retry-After-header van de throttle-middleware
    $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
    $wait = match (true) {
        $seconds <= 0 => 'even',
        $seconds < 60 => $seconds.' seconden',
        default => (int) ceil($seconds / 60).' '.(ceil($seconds / 60) == 1 ? 'minuut' : 'minuten'),
    };
@endphp

@section('code', '429')
@section('title', 'Even rustig aan')
@section('message')
Je hebt dit te vaak achter elkaar geprobeerd. Wacht {{ $wait }} en probeer het dan opnieuw.
@endsection
