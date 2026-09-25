@extends('theme-e803::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
