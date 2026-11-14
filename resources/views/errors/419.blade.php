@extends('errors.layout')

@section('code', '419')
@section('title', __('Session expired'))
@section('message')
{{ __('You were away for a while, so the page expired. Go back, refresh the page and try again.') }}
@endsection

