@extends('theme-ca0050::layout')
@php($pageTitle = data_get($category ?? null, 'name', __('Sản phẩm')))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
