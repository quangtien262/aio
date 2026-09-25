@extends('theme-shop602::layout') @section('title','Kết quả tìm kiếm') @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
