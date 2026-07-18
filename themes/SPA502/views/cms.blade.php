@extends('theme-spa502::layout')

@php
    $title = $pageTitle ?? data_get($page ?? null, 'title') ?? 'Nội dung';
    $body = data_get($page ?? null, 'body') ?? data_get($page ?? null, 'content') ?? '';
@endphp

@section('title', $title)

@section('content')
    <section class="spa502-page-head">
        <div class="spa502-container">
            <nav class="spa502-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span></nav>
            <h1>{{ $title }}</h1>
        </div>
    </section>
    <section class="spa502-subpage">
        <article class="spa502-container spa502-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
    </section>
@endsection
