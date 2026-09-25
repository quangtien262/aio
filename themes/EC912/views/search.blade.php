@extends('theme-ec912::layout')
@section('title', 'Tìm kiếm')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
