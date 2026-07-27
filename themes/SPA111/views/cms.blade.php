@extends('theme-spa111::layout')

@php
    $title = $pageTitle ?? data_get($page ?? null, 'title') ?? 'Nội dung';
    $body = data_get($page ?? null, 'body') ?? data_get($page ?? null, 'content') ?? '';
@endphp

@section('title', $title)

@section('content')
    <section class="spa111-page-head">
        <div class="spa111-container">
            <nav class="spa111-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span></nav>
            <h1>{{ $title }}</h1>
        </div>
    </section>
    <section class="spa111-subpage">
        <article class="spa111-container spa111-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
    </section>
@endsection
