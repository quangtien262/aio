@extends('theme-dn350::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content') @include('theme-dn350::partials.listing', ['title' => data_get($category ?? null, 'name', 'Sản phẩm')]) @endsection
