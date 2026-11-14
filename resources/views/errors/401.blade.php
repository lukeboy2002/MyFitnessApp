@extends('errors.layout')

@section('code', '401')
@section('title', __('Not logged in'))
@section('message')
{{ __('You need to be logged in to view this page.') }}
@endsection

@section('actions')
    <x-button :href="route('login')" icon="lock">{{ __('Log in') }}</x-button>
@endsection
