@php
    $xd5Branding = (array) data_get($themeShellData ?? $shell ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $xd5CompanyName = trim((string) ($companyName ?? $logoAlt ?? $xd5Branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $xd5SupportAddress = trim((string) ($supportAddress ?? $address ?? $xd5Branding['support_location'] ?? ''));
    $xd5SupportEmail = trim((string) ($supportEmail ?? $email ?? $xd5Branding['support_email'] ?? ''));
    $xd5Hotline = trim((string) ($hotline ?? $xd5Branding['support_hotline'] ?? ''));
    $xd5PhoneHref = trim((string) ($phoneHref ?? preg_replace('/\D+/', '', $xd5Hotline)));
    $xd5LogoUrl = trim((string) ($logoUrl ?? $xd5Branding['logo_url'] ?? ''));
@endphp

<header class="xd5-header">
    <div class="xd5-utility">
        <div class="xd5-container">
            <div class="xd5-utility-contact">
                @if(filled($xd5SupportAddress))<span>{{ $xd5SupportAddress }}</span>@endif
                @if(filled($xd5SupportEmail))<span>{{ $xd5SupportEmail }}</span>@endif
                @if(filled($xd5Hotline))<b>{{ $xd5Hotline }}</b>@endif
            </div>
            <div class="xd5-utility-actions">
                @guest('customer')
                    <button type="button" class="xd5-auth-link" data-xd-auth-open="login">Đăng nhập</button>
                    <span class="xd5-auth-separator">/</span>
                    <button type="button" class="xd5-auth-link" data-xd-auth-open="register">Đăng ký</button>
                @else
                    <a class="xd5-auth-link" href="{{ route('customer.account') }}">Tài khoản</a>
                @endguest
                <div class="xd5-language">@include('partials.storefront-language-switcher')</div>
            </div>
        </div>
    </div>
    <div class="xd5-container xd5-nav-wrap">
        <a class="xd5-brand" href="{{ route('site.home') }}">
            @if(filled($xd5LogoUrl))<img src="{{ $xd5LogoUrl }}" alt="{{ $xd5CompanyName }}">@endif
        </a>
        <button data-xd5-menu aria-expanded="false">Menu</button>
        <nav data-xd5-nav>
            @foreach($navItems ?? [] as $item)<a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>@endforeach
        </nav>
        @if(filled($xd5Hotline))<a class="xd5-hotline" href="tel:{{ $xd5PhoneHref }}">☎ {{ $xd5Hotline }}</a>@endif
    </div>
</header>
