@extends('theme-book920::layout')
@section('content')
@include('theme-book920::partials.listing', ['detailRoute' => 'site.services.show'])
@endsection
