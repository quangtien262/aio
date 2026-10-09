@extends('theme-ec903::layout')
@section('title', data_get($entry ?? null, 'title', 'Dịch vụ'))
@section('content')@include('theme-ec903::partials.content-shell')@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endsection
