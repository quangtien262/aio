@extends('theme-dn302::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="dn-inner-hero"><div class="dn-container"><h1>Đăng ký tư vấn</h1></div></section><section class="dn-section"><form class="dn-container dn-contact-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="dn302-contact"><input name="name" required placeholder="Họ và tên"><input type="email" name="email" required placeholder="Email"><input name="phone" required placeholder="Điện thoại"><textarea name="message" required minlength="10" placeholder="Nội dung"></textarea><button class="dn-btn">Gửi liên hệ</button></form></section></main>
@endsection
