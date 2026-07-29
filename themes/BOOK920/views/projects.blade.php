@extends('theme-book920::layout')
@section('title', 'Thư viện')
@section('content')
@include('theme-book920::partials.listing', ['title' => 'Thư viện Bookle', 'summary' => 'Những hoạt động và nội dung nổi bật từ cộng đồng Bookle.'])
@endsection
