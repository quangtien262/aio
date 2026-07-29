@extends('theme-book920::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1></div></section><section class="book20-content"><div class="book20-container book20-prose">{!! data_get($service ?? null, 'body', data_get($service ?? null, 'content')) !!}</div></section></main>
@endsection
