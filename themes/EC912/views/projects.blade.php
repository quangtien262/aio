@extends('theme-ec912::layout')
@section('title', 'Dự án')
@section('content')
@include('theme-ec912::partials.listing', ['title' => 'Dự án', 'summary' => 'Các dự án và hoạt động nổi bật.'])
@endsection
