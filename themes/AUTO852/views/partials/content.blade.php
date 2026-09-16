@extends('theme-auto852::layout')
@section('content')
<main><section class="a852-inner-hero"><div class="a852-container"><small>AUTO852</small><h1>{{ $pageTitle ?? data_get($entry ?? null, 'title', 'Thông tin') }}</h1></div></section><article class="a852-content"><div class="a852-container a852-prose">@if($image ?? null)<img src="{{ $image }}" alt="{{ $pageTitle ?? '' }}">@endif{!! $content ?? data_get($entry ?? null, 'content', data_get($entry ?? null, 'body', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'empty', 'Nội dung đang được cập nhật.'))) !!}</div></article></main>
@endsection
