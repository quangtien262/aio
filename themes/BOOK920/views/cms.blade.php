@extends('theme-book920::layout')
@section('content')
@include('theme-book920::partials.content')

@if(($contentType ?? '') === 'page')
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endif
@endsection
