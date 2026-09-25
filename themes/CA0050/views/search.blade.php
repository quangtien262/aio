@extends('theme-ca0050::layout')
@php($pageTitle = filled($searchQuery ?? '') ? __('Kết quả tìm kiếm') : __('Sản phẩm'))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
