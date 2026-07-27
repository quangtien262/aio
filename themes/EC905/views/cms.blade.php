@extends('theme-ec905::layout')
@section('title', data_get($page ?? null, 'title', 'Ego Home'))
@section('content')<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec95-content"><article class="ec95-container ec95-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection
