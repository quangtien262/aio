@extends('theme-auto853::layout') @section('title',$pageTitle??data_get($service??null,'title','Dịch vụ')) @section('content')@include('theme-auto853::partials.content-shell')@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endsection
