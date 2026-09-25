@extends('theme-auto852::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
