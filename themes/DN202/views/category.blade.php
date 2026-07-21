@extends('theme-dn202::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content') @include('theme-dn202::partials.listing', ['title' => data_get($category ?? null, 'name', 'Sản phẩm')]) @endsection
