@extends('theme-ec915::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-prose">{!! data_get($service ?? null, 'body', data_get($service ?? null, 'content')) !!}</div></section></main>
@endsection
