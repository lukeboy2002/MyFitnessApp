@extends('errors.layout')

@php
    // Hoe lang wachten? Staat in de Retry-After-header van de throttle-middleware
    $seconds = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
    $wait = match (true) {
        $seconds <= 0 => __('a moment'),
        $seconds < 60 => trans_choice(':count second|:count seconds', $seconds),
        default => trans_choice(':count minute|:count minutes', (int) ceil($seconds / 60)),
    };
@endphp

@section('code', '429')
@section('title', __('Easy does it'))
@section('message')
{{ __('You tried this too often in a row. Wait :wait and then try again.', ['wait' => $wait]) }}
@endsection
