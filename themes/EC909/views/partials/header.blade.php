@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $siteName = data_get($profile, 'site_name', 'Euro Sound');
    $logo = data_get($branding, 'logo_url');
    $hotline = data_get($branding, 'support_hotline', '0773915520');
    $email = data_get($branding, 'support_email', 'support@sapo.vn');
    $nav = collect($primaryMenu ?? [])->filter()->values();
@endphp
<header class="ec99-header" id="top">
    <div class="ec99-top"><div class="ec99-shell"><nav><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a></nav><div><i class="fa-solid fa-angle-left"></i><span>Giảm giá lên đến 50%</span><i class="fa-solid fa-angle-right"></i></div><aside><a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i> {{ $email }}</a><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> {{ $hotline }}</a></aside></div></div>
    <div class="ec99-nav-wrap"><div class="ec99-shell">
        <a class="ec99-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>Ξ</span><strong>EURO SOUND</strong>@endif</a>
        <button type="button" data-ec99-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        <nav data-ec99-nav>@forelse($nav as $index => $item)<a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@empty<a class="is-active" href="{{ route('site.home') }}">Trang chủ</a><a href="#goi-y">Nổi bật⌄</a><a href="{{ route('site.catalog.search') }}">Sản phẩm⌄</a><a href="{{ route('site.blog.index') }}">Giới thiệu</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.blog.index') }}">Tin tức</a><a href="{{ route('site.contact') }}">Hỗ trợ⌄</a>@endforelse</nav>
        <div class="ec99-actions"><button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button><a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a><a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>{{ data_get($cartSummary ?? [], 'count', 0) }}</b></a></div>
    </div></div>
</header>
