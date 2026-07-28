@extends('theme-ec100::layout')
@section('title', data_get($entry ?? null, 'title', 'Bộ sưu tập'))
@section('content')
@include('theme-ec100::partials.content-shell')
@endsection
