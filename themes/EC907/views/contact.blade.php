@extends('theme-ec907::layout')
@section('title', 'Liên hệ EGA Gear')
@section('content')
<main><section class="ec97-inner-hero"><div class="ec97-container"><p>EGA GEAR</p><h1>Liên hệ EGA Gear</h1></div></section><section class="ec97-content"><form class="ec97-container ec97-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec97-button">Gửi liên hệ</button></form></section></main>
@endsection

