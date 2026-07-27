@extends('theme-ec905::layout')
@section('title', 'Liên hệ Ego Home')
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>Liên hệ Ego Home</h1></div></section><section class="ec95-content"><form class="ec95-container ec95-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec95-button">Gửi liên hệ</button></form></section></main>
@endsection
