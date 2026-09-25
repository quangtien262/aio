@extends('theme-dl750::layout')
@section('title')@themeT('search', 'Tìm kiếm')@endsection
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
