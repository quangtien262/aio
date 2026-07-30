@extends('theme-bds701::layout')
@section('title')@themeT('contact', 'Liên hệ')@endsection
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>@themeT('contact.heading', 'Liên hệ tư vấn bất động sản')</h1></div></section>
<main class="bds-section"><div class="bds-container bds-newsletter-card"><div><h2>@themeT('contact.prompt', 'Hãy chia sẻ nhu cầu của bạn')</h2><p>@themeT('contact.summary', 'Đội ngũ tư vấn sẽ liên hệ và đề xuất bất động sản phù hợp.')</p></div><form method="POST" action="{{ route('site.contact.submit') }}" style="display:grid;gap:12px">@csrf<input name="name" required placeholder="@themeT('contact.name', 'Họ và tên')"><input name="phone" required placeholder="@themeT('contact.phone', 'Số điện thoại')"><input name="email" type="email" required placeholder="Email"><textarea name="message" required placeholder="@themeT('contact.message', 'Nhu cầu của bạn')"></textarea><button class="bds-btn">@themeT('contact.submit', 'Gửi yêu cầu')</button></form></div></main>
@endsection
