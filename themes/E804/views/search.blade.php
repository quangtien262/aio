@extends('theme-e804::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
