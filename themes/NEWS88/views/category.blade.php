@extends('theme-news88::layout')
@section('title', data_get($category ?? null, 'name', $pageTitle ?? 'Chuyên mục'))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
