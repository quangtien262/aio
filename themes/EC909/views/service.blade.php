@extends('theme-ec909::layout')
@section('title', data_get($service ?? null, 'title', data_get($service ?? null, 'name', 'Dịch vụ')))
@section('content')<main><section class="ec99-content"><article class="ec99-container ec99-prose"><h1>{{ data_get($service ?? null, 'title', data_get($service ?? null, 'name')) }}</h1>{!! data_get($service ?? null, 'body', data_get($service ?? null, 'description')) !!}</article></section></main>@endsection


