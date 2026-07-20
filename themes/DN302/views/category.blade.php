@extends('theme-dn302::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content') @include('theme-dn302::partials.listing', ['title' => data_get($category ?? null, 'name', 'Sản phẩm')]) @endsection
