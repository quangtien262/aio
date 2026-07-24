@extends('theme-bds701::layout')
@section('title', 'Liên hệ')
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>Liên hệ tư vấn bất động sản</h1></div></section>
<main class="bds-section"><div class="bds-container bds-newsletter-card"><div><h2>Hãy chia sẻ nhu cầu của bạn</h2><p>Đội ngũ tư vấn sẽ liên hệ và đề xuất bất động sản phù hợp.</p></div><form method="POST" action="{{ route('site.contact.submit') }}" style="display:grid;gap:12px">@csrf<input name="name" required placeholder="Họ và tên"><input name="phone" required placeholder="Số điện thoại"><input name="email" type="email" required placeholder="Email"><textarea name="message" required placeholder="Nhu cầu của bạn"></textarea><button class="bds-btn">Gửi yêu cầu</button></form></div></main>
@endsection
