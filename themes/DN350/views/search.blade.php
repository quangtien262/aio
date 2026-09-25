@extends('theme-dn350::layout')
@section('title', 'Kết quả tìm kiếm')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
