@extends('theme-ec903::layout')
@section('title', data_get($entry ?? null, 'title', 'Trải nghiệm'))
@section('content')@include('theme-ec903::partials.content-shell')@endsection
