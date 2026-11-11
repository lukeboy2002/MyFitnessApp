@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', 'Dat lukte niet')
@section('message')
Er ging iets mis met je verzoek. Ga terug en probeer het opnieuw.
@endsection

