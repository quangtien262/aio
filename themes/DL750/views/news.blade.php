@extends('theme-dl750::layout')
@section('content')
    @include('theme-dl750::partials.listing', ['detailRoute' => 'site.blog.show'])
@endsection
