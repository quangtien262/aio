@extends('theme-ec901::layout')
@section('title', data_get($entry ?? null, 'title', 'Tạp chí đồng hồ'))
@section('content')
@include('theme-ec901::partials.content-shell')
@endsection
