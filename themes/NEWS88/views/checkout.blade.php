@extends('theme-news88::layout')
@section('title', $pageTitle ?? 'Đăng ký')
@section('content')<main class="n88-inner"><div class="n88-container"><div class="n88-contact-card">@yield('checkout-content')<h1>{{ $pageTitle ?? 'Đăng ký nhận tin' }}</h1><p>@themeT('NEWS88.no_content', 'Nội dung đang được cập nhật.')</p></div></div></main>@endsection
