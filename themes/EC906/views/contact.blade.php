@extends('theme-ec906::layout')
@section('title', 'Liên hệ EGA Mini Mart')
@section('content')
<main><section class="ec96-inner-hero"><div class="ec96-container"><p>EGA MINI</p><h1>Liên hệ EGA Mini Mart</h1></div></section><section class="ec96-content"><form class="ec96-container ec96-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec96-button">Gửi liên hệ</button></form></section></main>
@endsection
