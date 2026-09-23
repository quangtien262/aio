@extends('theme-book920::layout')
@section('content')
@include('theme-book920::partials.catalog', ['catalogTitle' => data_get($category ?? null, 'name', $pageTitle ?? '')])
@endsection
