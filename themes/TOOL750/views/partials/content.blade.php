@extends('theme-tool750::layout')
@section('content')
<main>
    <section class="t750-inner-hero"><div class="t750-container"><small>TOOL750</small><h1>{{ $pageTitle ?? data_get($entry ?? null, 'title', 'Thông tin') }}</h1></div></section>
    <article class="t750-content"><div class="t750-container t750-prose">
        @if($image ?? null)<img src="{{ $image }}" alt="{{ $pageTitle ?? '' }}">@endif
        {!! $content ?? data_get($entry ?? null, 'content', data_get($entry ?? null, 'body', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'empty', 'Nội dung đang được cập nhật.'))) !!}
    </div></article>
</main>
@endsection
