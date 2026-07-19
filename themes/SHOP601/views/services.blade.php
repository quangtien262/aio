@extends('theme-shop601::layout')
@section('title', $pageTitle ?? 'Dịch vụ')
@section('content') @php($items = $services ?? []) @include('theme-shop601::partials.listing') @endsection
