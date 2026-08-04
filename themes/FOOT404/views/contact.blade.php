@extends('theme-foot404::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="f404-inner-hero"><div class="f404-container"><h1>Liên hệ</h1><p>Đội ngũ của chúng tôi luôn sẵn sàng hỗ trợ bạn.</p></div></section><section class="f404-content"><div class="f404-container f404-prose">{!! data_get($pageModel ?? $page ?? null, 'body', '<p>Vui lòng liên hệ qua hotline hoặc email hiển thị ở chân trang.</p>') !!}</div></section></main>
@endsection
