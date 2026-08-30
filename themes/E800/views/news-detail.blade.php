@extends('theme-e800::layout')
@section('title',$pageTitle??data_get($post??null,'title','Tin tức'))
@section('content')@include('theme-e800::partials.content-shell')@endsection
