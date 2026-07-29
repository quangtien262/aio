@extends('theme-book920::layout')
@section('title', 'Tin tức')
@section('content')
@include('theme-book920::partials.listing', ['title' => 'Tin tức Bookle', 'summary' => 'Cảm hứng đọc sách, giới thiệu tác phẩm và những câu chuyện mới.'])
@endsection
