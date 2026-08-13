@extends('theme-news88::layout')
@section('title', $pageTitle ?? 'Dịch vụ')
@section('content') @include('theme-news88::partials.listing', ['contentType' => 'services']) @endsection
