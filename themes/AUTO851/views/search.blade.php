@extends('theme-auto851::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
