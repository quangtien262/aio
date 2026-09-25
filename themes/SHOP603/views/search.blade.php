@extends('theme-shop603::layout') @section('title','Kết quả tìm kiếm') @section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
