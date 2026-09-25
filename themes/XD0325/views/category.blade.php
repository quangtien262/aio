@extends('theme-xd0325::layout') @section('title',data_get($category??null,'name','Sản phẩm')) @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
