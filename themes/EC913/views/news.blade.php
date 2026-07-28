@extends('theme-ec913::layout')
@section('title', 'Tin tức công nghệ')
@section('content')
@include('theme-ec913::partials.listing', ['title' => 'Tin tức công nghệ', 'summary' => 'Tin mới, đánh giá và kinh nghiệm sử dụng thiết bị.'])
@endsection
