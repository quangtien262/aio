@extends('theme-ec100::layout')
@section('title', 'Bộ sưu tập')
@section('content')
@include('theme-ec100::partials.listing', ['title' => 'Bộ sưu tập', 'summary' => 'Những tuyển chọn mang tinh thần riêng cho từng phong cách.'])
@endsection
