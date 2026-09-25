@extends('theme-e807::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
