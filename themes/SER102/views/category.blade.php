@extends('theme-ser102::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
