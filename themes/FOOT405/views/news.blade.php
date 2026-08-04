@extends('theme-foot405::layout')
@section('title', 'Tin tức')
@section('content')
@include('theme-foot405::partials.listing', ['title' => 'Tin tức', 'summary' => 'Thông tin hữu ích và những câu chuyện mới nhất.'])
@endsection
