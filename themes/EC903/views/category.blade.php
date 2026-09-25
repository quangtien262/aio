@extends('theme-ec903::layout')
@section('title', data_get($category ?? null, 'name', 'Deal'))
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'category'])
@endsection
