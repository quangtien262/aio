@extends('theme-foot404::layout')
@section('title', 'Tin tức')
@section('content')
@include('theme-foot404::partials.listing', ['title' => 'Tin tức', 'summary' => 'Thông tin hữu ích và những câu chuyện mới nhất.'])
@endsection
