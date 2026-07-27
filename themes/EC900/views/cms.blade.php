@extends('theme-ec900::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
@include('theme-ec900::partials.content-shell')
@endsection
