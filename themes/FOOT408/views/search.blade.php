@extends('theme-foot408::layout') @section('title','Tìm kiếm món ăn') @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
