@extends('theme-dl750::layout')
@section('title', data_get($category ?? null, 'name', ''))
@section('content')
    @include('theme-dl750::partials.catalog', ['catalogTitle' => data_get($category ?? null, 'name', '')])
@endsection
