@extends('theme-ca0050::layout')
@section('content')
@include('theme-ca0050::partials.editorial', ['article' => $entry, 'isService' => false])

@if(($contentType ?? '') === 'page')
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endif
@endsection
