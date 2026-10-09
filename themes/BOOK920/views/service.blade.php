@extends('theme-book920::layout')
@section('content')
@include('theme-book920::partials.content')
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endsection
