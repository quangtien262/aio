@extends('theme-book920::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="book20-content"><article class="book20-container book20-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
