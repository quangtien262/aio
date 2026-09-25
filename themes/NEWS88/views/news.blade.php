@extends('theme-news88::layout')
@section('title', $pageTitle ?? __('NEWS88.latest'))
@section('content')
@include('themes.common.news-listing')
@endsection
