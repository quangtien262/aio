@php $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm')); $cover = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url')); $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description')); @endphp
@extends('theme-dn202::layout')
@section('title', $title)
@section('content') @include('theme-dn202::partials.content-shell', ['title' => $title, 'cover' => $cover, 'body' => $body]) @endsection
