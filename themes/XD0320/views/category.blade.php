@extends('theme-xd0320::layout')
@push('head')
@include('theme-xd0320::partials.catalog-styles')
@endpush
@section('content')
@include('theme-xd0320::partials.catalog', ['catalogMode' => 'category'])
@endsection
