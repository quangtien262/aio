@extends('theme-ec916::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>Liên hệ</h1></div></section><section class="ec16-content"><form class="ec16-container ec16-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec16-button">Gửi liên hệ</button></form></section></main>
@endsection
