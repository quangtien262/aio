@extends('theme-ec901::layout')
@section('title', data_get($entry ?? null, 'title', 'Dịch vụ'))
@section('content')
@include('theme-ec901::partials.content-shell')
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endsection
