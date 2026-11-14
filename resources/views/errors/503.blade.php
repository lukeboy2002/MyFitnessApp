@extends('errors.layout')

@section('code', '503')
@section('title', __('Maintenance'))
@section('message')
{{ __('We are updating the app. You can continue in a few minutes.') }}
@endsection

@section('actions')
    <x-button type="button" icon="clock" onclick="location.reload()">{{ __('Try again') }}</x-button>
@endsection
