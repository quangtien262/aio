@extends('theme-ec915::layout')
@section('title', 'Dự án')
@section('content')
@include('theme-ec915::partials.listing', ['title' => 'Dự án', 'summary' => 'Các dự án và hoạt động nổi bật.'])
@endsection
