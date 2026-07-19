@extends('theme-ser102::layout')

@php
    $title = $pageTitle ?? data_get($page ?? null, 'title') ?? 'Nội dung';
    $body = data_get($page ?? null, 'body') ?? data_get($page ?? null, 'content') ?? '';
@endphp

@section('title', $title)

@section('content')
    <section class="ser102-page-head">
        <div class="ser102-container">
            <nav class="ser102-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span></nav>
            <h1>{{ $title }}</h1>
        </div>
    </section>
    <section class="ser102-subpage">
        <article class="ser102-container ser102-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
    </section>
@endsection
