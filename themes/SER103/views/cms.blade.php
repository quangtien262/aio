@extends('theme-ser103::layout')

@php
    $title = $pageTitle ?? data_get($page ?? null, 'title') ?? 'Nội dung';
    $body = data_get($page ?? null, 'body') ?? data_get($page ?? null, 'content') ?? '';
@endphp

@section('title', $title)

@section('content')
    <section class="ser103-page-head">
        <div class="ser103-container">
            <nav class="ser103-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span></nav>
            <h1>{{ $title }}</h1>
        </div>
    </section>
    <section class="ser103-subpage">
        <article class="ser103-container ser103-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
    </section>
@endsection
