@extends('theme-tool751::layout')
@section('content')
<main><section class="t751-inner-hero"><div class="t751-container"><small>TOOL751</small><h1>{{ $pageTitle ?? data_get($entry ?? null, 'title', 'Thông tin') }}</h1></div></section><article class="t751-content"><div class="t751-container t751-prose">@if($image ?? null)<img src="{{ $image }}" alt="{{ $pageTitle ?? '' }}">@endif{!! $content ?? data_get($entry ?? null, 'content', data_get($entry ?? null, 'body', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'empty', 'Nội dung đang được cập nhật.'))) !!}</div></article></main>
@endsection
