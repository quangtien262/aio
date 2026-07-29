@extends('theme-book920::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>Liên hệ Bookle</h1></div></section><section class="book20-content"><form class="book20-container book20-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="book20-button">Gửi liên hệ</button></form></section></main>
@endsection
