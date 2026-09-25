@extends('theme-foot405::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục sản phẩm'))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
