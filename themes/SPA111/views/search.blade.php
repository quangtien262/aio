@extends('theme-spa111::layout')

@php
    $source = $products ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $pageTitle = $pageTitle ?? 'Sản phẩm';
@endphp

@section('title', $pageTitle)

@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
