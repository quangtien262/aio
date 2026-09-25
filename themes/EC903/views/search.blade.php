@extends('theme-ec903::layout')
@section('title', 'Tìm kiếm deal')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
