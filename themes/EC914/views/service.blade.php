@extends('theme-ec914::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-prose">{!! data_get($service ?? null, 'body', data_get($service ?? null, 'content')) !!}</div></section></main>
@endsection
