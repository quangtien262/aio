@extends('theme-ec912::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="ec12-inner-hero"><div class="ec12-container"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1></div></section><section class="ec12-content"><div class="ec12-container ec12-prose">{!! data_get($service ?? null, 'body', data_get($service ?? null, 'content')) !!}</div></section></main>
@endsection
