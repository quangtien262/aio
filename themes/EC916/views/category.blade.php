@extends('theme-ec916::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
