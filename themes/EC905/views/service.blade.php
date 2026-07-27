@extends('theme-ec905::layout')
@section('title', data_get($service ?? null, 'title', data_get($service ?? null, 'name', 'Dịch vụ')))
@section('content')<main><section class="ec95-content"><article class="ec95-container ec95-prose"><h1>{{ data_get($service ?? null, 'title', data_get($service ?? null, 'name')) }}</h1>{!! data_get($service ?? null, 'body', data_get($service ?? null, 'description')) !!}</article></section></main>@endsection
