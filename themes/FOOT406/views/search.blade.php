@extends('theme-foot406::layout') @section('title','Tìm kiếm sản phẩm') @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
