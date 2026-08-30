@extends('theme-e800::layout')
@section('title',$pageTitle??data_get($service??null,'title','Dịch vụ'))
@section('content')@include('theme-e800::partials.content-shell')@endsection
