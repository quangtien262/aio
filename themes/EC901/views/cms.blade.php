@extends('theme-ec901::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
@include('theme-ec901::partials.content-shell')
@endsection
