@php
    $activeNav = $activeNav ?? 'home';
    $topMenu = $topMenu ?? [];
    $customerAuth = $customerAuth ?? ['is_authenticated' => false];
    $newsletterState = $newsletterState ?? ['is_subscribed' => false];
    $cartSummary = $cartSummary ?? ['count' => 0];
    $contactHotline = $contactHotline ?? data_get($branding ?? [], 'support_hotline', '1900 6760');
    $contactEmail = $contactEmail ?? data_get($branding ?? [], 'support_email', 'sales@lan0201.demo');
    $contactLocation = $contactLocation ?? data_get($branding ?? [], 'support_location', 'TP.HCM');
@endphp
<div class="th-landing-topbar">
    <div class="th-landing-container th-landing-topbar-inner">
        <div class="th-landing-inline">
            <span>{{ $contactLocation }}</span>
            <span>{{ $contactHotline }}</span>
            <span>{{ $contactEmail }}</span>
        </div>
        <div class="th-landing-inline">
            <button type="button" class="th-landing-inline-action" data-open-newsletter-modal>
                {{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}
            </button>
            @auth('admin')
                <a href="{{ route('admin.index') }}">Quản trị</a>
            @endauth
        </div>
    </div>
</div>

<header class="th-landing-header">
    <div class="th-landing-container th-landing-header-inner">
        <a class="th-landing-brand" href="{{ route('site.home') }}">
            <img src="{{ data_get($branding ?? [], 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding ?? [], 'company_name', 'LAN0201') }}">
        </a>

        <nav class="th-landing-nav">
            <a href="{{ route('site.home') }}" class="{{ $activeNav === 'home' ? 'is-active' : '' }}">Tổng quan</a>
            @foreach (collect($topMenu)->take(4) as $item)
                <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? __('common.menu') }}</a>
            @endforeach
            <a href="{{ route('site.blog.index') }}" class="{{ $activeNav === 'cms' ? 'is-active' : '' }}">Tin dự án</a>
            <a href="{{ route('site.cart.index') }}" class="{{ $activeNav === 'cart' ? 'is-active' : '' }}">Quan tâm ({{ $cartSummary['count'] ?? 0 }})</a>
        </nav>

        <div class="th-landing-actions">
            @if (!empty($customerAuth['is_authenticated']))
                <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}" class="th-landing-outline">Tài khoản</a>
                <form method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                    @csrf
                    <button type="submit" class="th-landing-ghost">Đăng xuất</button>
                </form>
            @else
                <button type="button" class="th-landing-ghost" data-open-auth-modal="register">Đăng ký</button>
                <button type="button" class="th-landing-outline" data-open-auth-modal="login" data-auth-redirect="{{ route('admin.index') }}">Quản trị</button>
                <button type="button" class="th-landing-button" data-open-auth-modal="login">Đăng nhập</button>
            @endif
        </div>
    </div>
</header>