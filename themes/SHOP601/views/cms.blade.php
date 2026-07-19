@extends('theme-shop601::layout')
@section('title', data_get($page ?? null, 'title', 'SHOP601'))
@section('content') @include('theme-shop601::partials.content-shell') @endsection
