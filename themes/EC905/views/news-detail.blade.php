@extends('theme-ec905::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>TIN TỨC EGO HOME</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec95-content"><article class="ec95-container ec95-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection
