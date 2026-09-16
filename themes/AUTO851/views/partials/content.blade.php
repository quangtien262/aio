@extends('theme-auto851::layout')
@section('content')
<main><section class="a851-inner-hero"><div class="a851-container"><small>AUTO851</small><h1>{{ $pageTitle ?? data_get($entry ?? null, 'title', 'Thông tin') }}</h1></div></section><article class="a851-content"><div class="a851-container a851-prose">@if($image ?? null)<img src="{{ $image }}" alt="{{ $pageTitle ?? '' }}">@endif{!! $content ?? data_get($entry ?? null, 'content', data_get($entry ?? null, 'body', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'empty', 'Nội dung đang được cập nhật.'))) !!}</div></article></main>
@endsection
