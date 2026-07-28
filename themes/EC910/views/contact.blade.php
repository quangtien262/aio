@extends('theme-ec100::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="ec10-inner-hero"><div class="ec10-container"><p>TEMPO WATCH STORE</p><h1>Liên hệ Tempo</h1></div></section><section class="ec10-content"><form class="ec10-container ec10-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec10-button">Gửi liên hệ</button></form></section></main>
@endsection
