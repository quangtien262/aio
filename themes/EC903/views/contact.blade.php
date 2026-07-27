@extends('theme-ec903::layout')
@section('title', 'Liên hệ')
@section('content')
<main><section class="ec93-inner-hero"><div class="ec93-container"><p>DEALVUI E-VOUCHER</p><h1>Liên hệ DealVui</h1></div></section><section class="ec93-content"><form class="ec93-container ec93-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec93-button">Gửi liên hệ</button></form></section></main>
@endsection
