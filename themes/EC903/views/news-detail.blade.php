@extends('theme-ec903::layout')
@section('title', data_get($entry ?? null, 'title', 'Cẩm nang ưu đãi'))
@section('content')@include('theme-ec903::partials.content-shell')@endsection
