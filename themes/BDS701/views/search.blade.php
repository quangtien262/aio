@extends('theme-bds701::layout')
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
