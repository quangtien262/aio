@extends('theme-ec100::layout')
@section('title', data_get($entry ?? null, 'title', 'Tạp chí đồng hồ'))
@section('content')
@include('theme-ec100::partials.content-shell')
@endsection
