@extends('errors.layout')

@section('code', '404')
@section('title', __('Not found'))
@section('message')
{{ __('This page does not exist (anymore). Maybe the link is outdated or the item was deleted.') }}
@endsection

