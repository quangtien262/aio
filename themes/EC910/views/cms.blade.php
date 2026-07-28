@extends('theme-ec100::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
@include('theme-ec100::partials.content-shell')
@endsection
