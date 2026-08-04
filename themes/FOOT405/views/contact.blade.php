@extends('theme-foot405::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="f405-inner-hero"><div class="f405-container"><h1>Liên hệ</h1><p>Đội ngũ của chúng tôi luôn sẵn sàng hỗ trợ bạn.</p></div></section><section class="f405-content"><div class="f405-container f405-prose">{!! data_get($pageModel ?? $page ?? null, 'body', '<p>Vui lòng liên hệ qua hotline hoặc email hiển thị ở chân trang.</p>') !!}</div></section></main>
@endsection
