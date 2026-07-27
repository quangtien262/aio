@extends('theme-ec903::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')@include('theme-ec903::partials.content-shell')@endsection
