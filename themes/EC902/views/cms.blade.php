@extends('theme-ec902::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
@include('theme-ec902::partials.content-shell')
@endsection
