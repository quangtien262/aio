@extends('theme-dn202::layout')
@section('title', $pageTitle ?? 'Tin tức')
@section('content')
@include('themes.common.news-listing')
@endsection
