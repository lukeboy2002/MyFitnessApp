@extends('errors.layout')

@section('code', $exception->getStatusCode())
@section('title', __('Something went wrong'))
@section('message')
{{ __('Something went wrong on our side. Try again in a moment. Does it keep happening? Let the administrator know.') }}
@endsection

