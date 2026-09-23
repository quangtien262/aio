@extends('theme-book920::layout')
@section('content')
@php $contactBrand = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? null, 'branding', [])); @endphp
<main class="book20-inner"><div class="book20-container">
    @include('theme-book920::partials.breadcrumb', ['current' => ''])
    <header class="book20-inner-hero"><p class="book20-kicker">@themeT('inner.contact_kicker', 'Luôn sẵn sàng lắng nghe')</p><h1>@themeT('BOOK920.contact', 'Liên hệ')</h1><p>@themeT('inner.contact_intro', 'Tìm sách, hỏi về đơn hàng hoặc chia sẻ nhu cầu của bạn với chúng tôi.')</p></header>
    @include('theme-book920::partials.feedback')
    <div class="book20-checkout-layout"><form class="book20-panel book20-form-grid" method="post" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact">
        <label>@themeT('inner.name', 'Họ và tên')<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label><label>Email<input name="email" type="email" autocomplete="email" maxlength="150" value="{{ old('email') }}" required></label>
        <label>@themeT('inner.phone', 'Số điện thoại')<input name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"></label><label>@themeT('inner.subject', 'Chủ đề')<input name="subject" maxlength="150" value="{{ old('subject') }}"></label>
        <label class="book20-wide">@themeT('inner.message', 'Nội dung cần hỗ trợ')<textarea name="message" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea></label><button class="book20-button" type="submit">@themeT('inner.send', 'Gửi liên hệ') &rarr;</button>
    </form><aside class="book20-note"><h2>{{ data_get($siteProfile ?? null, 'site_name') }}</h2>
        @if(data_get($contactBrand, 'support_hotline'))<p><small>@themeT('inner.phone', 'Số điện thoại')</small><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactBrand['support_hotline']) }}">{{ $contactBrand['support_hotline'] }}</a></p>@endif
        @if(data_get($contactBrand, 'support_email'))<p><small>Email</small><a href="mailto:{{ $contactBrand['support_email'] }}">{{ $contactBrand['support_email'] }}</a></p>@endif
        @if(data_get($contactBrand, 'support_location'))<p><small>@themeT('inner.location', 'Địa chỉ')</small>{{ $contactBrand['support_location'] }}</p>@endif
        @if(data_get($entry ?? null, 'body'))<div class="book20-prose">{!! $entry->body !!}</div>@endif
    </aside></div>
</div></main>
@endsection
