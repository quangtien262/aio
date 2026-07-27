@extends('theme-ec902::layout')
@section('title', data_get($entry ?? null, 'title', 'Tin công nghệ'))
@section('content')
@include('theme-ec902::partials.content-shell')
@endsection
