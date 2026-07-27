@extends('theme-ec908::layout')
@section('title', data_get($page ?? null, 'title', 'Ego Fitness'))
@section('content')<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec98-content"><article class="ec98-container ec98-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection


