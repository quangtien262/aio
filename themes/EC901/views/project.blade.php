@extends('theme-ec901::layout')
@section('title', data_get($entry ?? null, 'title', 'Bộ sưu tập'))
@section('content')
@include('theme-ec901::partials.content-shell')
@endsection
