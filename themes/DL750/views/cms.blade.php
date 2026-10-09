@extends('theme-dl750::layout')
@section('content')
    @include('theme-dl750::partials.content')

@if(($contentType ?? '') === 'page')
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endif
@endsection
