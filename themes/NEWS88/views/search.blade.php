@extends('theme-news88::layout')
@section('title', $pageTitle ?? 'Kết quả tìm kiếm')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
