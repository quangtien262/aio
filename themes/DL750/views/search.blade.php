@extends('theme-dl750::layout')
@section('title')@themeT('search', 'Tìm kiếm')@endsection
@section('content')
    @include('theme-dl750::partials.catalog', ['catalogTitle' => $searchQuery ?? ''])
@endsection
