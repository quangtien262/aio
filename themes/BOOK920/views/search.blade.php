@extends('theme-book920::layout')
@section('content')

@include('theme-book920::partials.catalog', ['catalogTitle' => $pageTitle ?? ''])
@endsection
