@extends('theme-bds701::layout')
@section('title', data_get($contentEntry ?? null, 'title', data_get($page ?? null, 'title', app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'pages.content', 'Nội dung'))))
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>{{ data_get($contentEntry ?? null, 'title', data_get($page ?? null, 'title')) }}</h1></div></section>
<main class="bds-section"><article class="bds-container bds-content-card">{!! data_get($contentEntry ?? null, 'body', data_get($page ?? null, 'body')) !!}</article></main>
@endsection
