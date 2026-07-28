@extends('theme-ec912::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec12-inner-hero"><div class="ec12-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="ec12-content"><article class="ec12-container ec12-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
