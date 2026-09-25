@extends('theme-foot408::layout') @section('title',data_get($category??null,'name','Danh mục món ăn')) @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
