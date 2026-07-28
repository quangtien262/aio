@extends('theme-ec911::layout')
@section('title', data_get($page ?? null, 'title', 'DIGITECH'))
@section('content')<main><section class="ec97-inner-hero"><div class="ec97-container"><p>EGA GEAR</p><h1>{{ data_get($page ?? null, 'title') }}</h1></div></section><section class="ec97-content"><article class="ec97-container ec97-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'excerpt')) !!}</article></section></main>@endsection


