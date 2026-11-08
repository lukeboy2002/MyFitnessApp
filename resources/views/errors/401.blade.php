@extends('errors.layout')

@section('code', '401')
@section('title', 'Niet ingelogd')
@section('message')
Je moet ingelogd zijn om deze pagina te bekijken.
@endsection

@section('actions')
    <x-button :href="route('login')" icon="lock">Inloggen</x-button>
@endsection
