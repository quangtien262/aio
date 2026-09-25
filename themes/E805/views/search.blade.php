@extends('theme-e805::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
