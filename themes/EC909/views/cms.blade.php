@extends('theme-ec909::layout')
@section('title', data_get($page ?? null, 'title', 'Ego Fitness'))
@section('content')<main><section class="ec99-inner-hero"><div class="ec99-container"><p>EGO FITNESS</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec99-content"><article class="ec99-container ec99-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection



