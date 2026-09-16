@extends('theme-auto850::layout')
@section('content')
<main><section class="a850-inner-hero"><div class="a850-container"><small>AUTO850</small><h1>{{ $pageTitle ?? data_get($entry ?? null, 'title', 'Thông tin') }}</h1></div></section><article class="a850-content"><div class="a850-container a850-prose">@if($image ?? null)<img src="{{ $image }}" alt="{{ $pageTitle ?? '' }}">@endif{!! $content ?? data_get($entry ?? null, 'content', data_get($entry ?? null, 'body', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'empty', 'Nội dung đang được cập nhật.'))) !!}</div></article></main>
@endsection
