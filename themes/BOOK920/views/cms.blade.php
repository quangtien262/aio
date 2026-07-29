@extends('theme-book920::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>{{ data_get($page ?? null, 'title', 'Nội dung') }}</h1></div></section><section class="book20-content"><div class="book20-container book20-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
