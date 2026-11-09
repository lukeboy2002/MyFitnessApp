@extends('errors.layout')

@php
    // Eigen melding tonen (bv. abort(403, 'Je kunt je eigen adminrechten niet afnemen.')), niet de Engelse standaardtekst
    $reason = $exception->getMessage();
    $reason = in_array($reason, ['', 'Forbidden', 'This action is unauthorized.'], true) ? null : $reason;

    // Verlopen of aangepaste link (bv. de bevestigingsmail, die 60 minuten geldig is)
    if ($exception instanceof \Illuminate\Routing\Exceptions\InvalidSignatureException) {
        $reason = 'Deze link is verlopen of klopt niet meer. Log in, dan kun je een nieuwe link laten sturen.';
    }
@endphp

@section('code', '403')
@section('title', 'Geen toegang')
@section('message')
{{ $reason ?? 'Je hebt geen toegang tot deze pagina. Misschien hoort hij bij een ander account, of is hij alleen voor beheerders.' }}
@endsection
