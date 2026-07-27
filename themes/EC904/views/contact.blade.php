@extends('theme-ec904::layout')
@section('title', 'Liên hệ PocoMall')
@section('content')
<main><section class="ec94-inner-hero"><div class="ec94-container"><p>POCOMALL</p><h1>Liên hệ PocoMall</h1></div></section><section class="ec94-content"><form class="ec94-container ec94-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec94-button">Gửi liên hệ</button></form></section></main>
@endsection
