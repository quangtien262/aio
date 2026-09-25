@extends('theme-bds701::layout')
@section('title')@themeT('pages.news_title', 'Tin tức bất động sản')@endsection
@section('content')
@include('themes.common.news-listing')
@endsection
