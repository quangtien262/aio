@extends('theme-book920::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
