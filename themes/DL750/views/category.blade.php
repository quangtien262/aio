@extends('theme-dl750::layout')
@section('title', data_get($category ?? null, 'name', ''))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
