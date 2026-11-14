@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', __('That did not work'))
@section('message')
    {{ __('Something went wrong with your request. Go back and try again.') }}
@endsection

