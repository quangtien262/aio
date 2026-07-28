@extends('theme-ec914::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="ec14-content"><article class="ec14-container ec14-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
