@extends('theme-ec906::layout')
@section('title', data_get($page ?? null, 'title', 'EGA Mini Mart'))
@section('content')<main><section class="ec96-inner-hero"><div class="ec96-container"><p>EGA MINI</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec96-content"><article class="ec96-container ec96-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection

