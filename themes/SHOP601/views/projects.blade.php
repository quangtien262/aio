@extends('theme-shop601::layout')
@section('title', $pageTitle ?? 'Dự án')
@section('content') @php($items = $projects ?? []) @include('theme-shop601::partials.listing') @endsection
