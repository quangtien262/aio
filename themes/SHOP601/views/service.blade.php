@extends('theme-shop601::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content') @include('theme-shop601::partials.content-shell') @endsection
