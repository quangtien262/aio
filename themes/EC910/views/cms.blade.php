@extends('theme-ec910::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
@include('theme-ec910::partials.content-shell')
@endsection
