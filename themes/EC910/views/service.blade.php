@extends('theme-ec100::layout')
@section('title', data_get($entry ?? null, 'title', 'Dịch vụ'))
@section('content')
@include('theme-ec100::partials.content-shell')
@endsection
