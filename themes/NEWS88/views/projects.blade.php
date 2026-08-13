@extends('theme-news88::layout')
@section('title', $pageTitle ?? 'Thư viện')
@section('content') @include('theme-news88::partials.listing', ['contentType' => 'projects']) @endsection
