@extends('theme-spa502::layout')

@php
    $source = $posts ?? $entries ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $pageTitle = $pageTitle ?? 'Tin tức';
@endphp

@section('title', $pageTitle)

@section('content')
@include('themes.common.news-detail')
@endsection
