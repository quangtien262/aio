@extends('theme-ec909::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec99-inner-hero"><div class="ec99-container"><p>TIN TỨC EURO SOUND</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec99-content"><article class="ec99-container ec99-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection


