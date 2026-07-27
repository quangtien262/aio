@extends('theme-ec904::layout')
@section('title', data_get($page ?? null, 'title', 'PocoMall'))
@section('content')<main><section class="ec94-inner-hero"><div class="ec94-container"><p>POCOMALL</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec94-content"><article class="ec94-container ec94-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection
