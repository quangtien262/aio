@extends('theme-ec911::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec97-inner-hero"><div class="ec97-container"><p>TIN TỨC EGA GEAR</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec97-content"><article class="ec97-container ec97-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection

