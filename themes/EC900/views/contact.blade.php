@extends('theme-ec900::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="ec9-inner-hero"><div class="ec9-container"><p>ECOMAX SMART HOME</p><h1>Liên hệ Ecomax</h1></div></section><section class="ec9-content"><form class="ec9-container ec9-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec9-button">Gửi liên hệ</button></form></section></main>
@endsection
