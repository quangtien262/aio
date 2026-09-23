@extends('theme-dl750::layout')
@section('content')
    @include('theme-dl750::partials.listing', ['detailRoute' => 'site.projects.show'])
@endsection
