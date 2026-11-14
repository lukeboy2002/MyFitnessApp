@extends('errors.layout')

@php
    // Eigen melding tonen (bv. abort(403, 'Je kunt je eigen adminrechten niet afnemen.')), niet de Engelse standaardtekst
    $reason = $exception->getMessage();
    $reason = in_array($reason, ['', 'Forbidden', 'This action is unauthorized.'], true) ? null : $reason;

    // Verlopen of aangepaste link (bv. de bevestigingsmail, die 60 minuten geldig is)
    if ($exception instanceof \Illuminate\Routing\Exceptions\InvalidSignatureException) {
        $reason = __('This link has expired or is no longer valid. Log in, then you can have a new link sent.');
    }
@endphp

@section('code', '403')
@section('title', __('No access'))
@section('message')
{{ $reason ?? __('You do not have access to this page. Maybe it belongs to another account, or it is only for administrators.') }}
@endsection
