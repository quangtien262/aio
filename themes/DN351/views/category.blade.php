@extends('theme-dn351::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content') @include('theme-dn351::partials.listing', ['title' => data_get($category ?? null, 'name', 'Sản phẩm')]) @endsection
