@extends('theme-ec908::layout')
@section('title', 'Liên hệ Ego Fitness')
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>Liên hệ Ego Fitness</h1></div></section><section class="ec98-content"><form class="ec98-container ec98-form" method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><input name="name" required placeholder="Họ và tên"><input name="email" type="email" required placeholder="Email"><input name="phone" placeholder="Số điện thoại"><textarea name="message" minlength="10" required placeholder="Nội dung"></textarea><button class="ec98-button">Gửi liên hệ</button></form></section></main>
@endsection

