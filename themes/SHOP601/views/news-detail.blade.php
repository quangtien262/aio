@extends('theme-shop601::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content') @include('theme-shop601::partials.content-shell') @endsection
