@extends('theme-bz501::layout')

@php
    $title = $pageTitle ?? data_get($page ?? null, 'title') ?? 'Nội dung';
    $body = data_get($page ?? null, 'body') ?? data_get($page ?? null, 'content') ?? '';
@endphp

@section('title', $title)

@section('content')
    <section class="bz501-page-head">
        <div class="bz501-container">
            <nav class="bz501-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span></nav>
            <h1>{{ $title }}</h1>
        </div>
    </section>
    <section class="bz501-subpage">
        <article class="bz501-container bz501-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
    </section>
@endsection
