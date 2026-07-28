@extends('theme-ec914::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>{{ data_get($page ?? null, 'title', 'Nội dung') }}</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
