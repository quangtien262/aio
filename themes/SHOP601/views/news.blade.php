@extends('theme-shop601::layout')
@section('title', $pageTitle ?? 'Tin tức')
@section('content') @php($items = $posts ?? []) @include('theme-shop601::partials.listing') @endsection
