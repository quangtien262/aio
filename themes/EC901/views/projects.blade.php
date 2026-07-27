@extends('theme-ec901::layout')
@section('title', 'Bộ sưu tập')
@section('content')
@include('theme-ec901::partials.listing', ['title' => 'Bộ sưu tập', 'summary' => 'Những tuyển chọn mang tinh thần riêng cho từng phong cách.'])
@endsection
