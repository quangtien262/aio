@extends('theme-ec906::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec96-inner-hero"><div class="ec96-container"><p>TIN TỨC EGA MINI</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec96-content"><article class="ec96-container ec96-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection
