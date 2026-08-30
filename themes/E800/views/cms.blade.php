@extends('theme-e800::layout')
@section('title',$pageTitle??data_get($page??null,'title','Nội dung'))
@section('content')@include('theme-e800::partials.content-shell')@endsection
