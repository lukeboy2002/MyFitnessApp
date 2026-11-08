@extends('errors.layout')

@section('code', '503')
@section('title', 'Even onderhoud')
@section('message')
We werken de app bij. Over een paar minuten kun je weer verder.
@endsection

@section('actions')
    <x-button type="button" icon="clock" onclick="location.reload()">Opnieuw proberen</x-button>
@endsection
