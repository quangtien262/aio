@extends('theme-ec909::layout')
@section('title', 'Liên hệ Euro Sound')
@section('content')
<main><section class="ec99-inner-hero"><div class="ec99-container"><p>EURO SOUND</p><h1>Liên hệ Euro Sound</h1></div></section><section class="ec99-content"><form class="ec99-container ec99-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec99-button">Gửi liên hệ</button></form></section></main>
@endsection


