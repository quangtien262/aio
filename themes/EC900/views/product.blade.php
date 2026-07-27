@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec900::layout')
@section('title', $title)
@section('content')
@include('theme-ec900::partials.content-shell', ['title' => $title, 'cover' => $image, 'body' => $body])
@endsection
