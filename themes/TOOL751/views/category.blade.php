@extends('theme-tool751::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
