@extends('theme-foot404::layout')
@section('title', 'Bộ sưu tập')
@section('content')
@include('theme-foot404::partials.listing', ['title' => 'Bộ sưu tập', 'summary' => 'Khám phá những lựa chọn nổi bật.'])
@endsection
