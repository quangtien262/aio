@extends('theme-ec910::layout')
@section('title', data_get($entry ?? null, 'title', 'Dịch vụ'))
@section('content')
@include('theme-ec910::partials.content-shell')
@endsection
