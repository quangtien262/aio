@extends('theme-news88::layout')
@section('title', $pageTitle ?? __('NEWS88.latest'))
@section('content') @include('theme-news88::partials.listing', ['contentType' => 'posts']) @endsection
