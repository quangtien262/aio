@php
    $documentHomeData = $themeHomeData ?? [];
    $documentShellData = $themeShellData ?? $documentHomeData;
    $documentBranding = $documentShellData['branding'] ?? $documentHomeData['branding'] ?? data_get($siteProfile ?? [], 'branding', []);
    $documentThemeKey = strtoupper((string) data_get($activeTheme ?? [], 'key', data_get($landingPage ?? [], 'theme_key', 'corporate-starter')));
    $documentViewNamespace = 'theme-'.strtolower($documentThemeKey);
    $documentMenu = collect(data_get($documentHomeData, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))
        ->whenEmpty(fn () => collect($landingMenuItems ?? []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? null))
        ->values();
    $customerAuth = $documentShellData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $documentShellData['newsletter'] ?? ['is_subscribed' => false];
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-storefront-head
    :site-profile="$siteProfile ?? null"
    :theme-shell-data="$documentShellData"
    :active-theme="$activeTheme ?? null"
    :landing-page="$landingPage ?? null"
    :page-title="data_get($landingPage ?? [], 'meta_title') ?: data_get($landingPage ?? [], 'title')"
    :page-description="$pageDescription ?? null"
    :page-keywords="$pageKeywords ?? null"
    :canonical-url="$canonicalUrl ?? null"
    :hreflang-urls="$hreflangUrls ?? []"
    :is-preview="$isPreview ?? false"
>
    @vite('resources/css/app.css')
        <style>
            :root{--th-landing-accent:{{ data_get($documentBranding,'primary_color','#0f3557') }};--th-landing-accent-deep:{{ data_get($documentBranding,'primary_color_deep','#0a2741') }};--th-surface:{{ data_get($documentBranding,'surface_color','#fff') }}}
            *{box-sizing:border-box}body{margin:0;background:{{ data_get($documentBranding,'background_color','#f5f1ea') }};color:#172033;font-family:Segoe UI,sans-serif}a{color:inherit;text-decoration:none}.aio-doc-container{width:min(1200px,calc(100% - 28px));margin:auto}.aio-doc-header{position:sticky;top:0;z-index:100;background:rgba(255,255,255,.94);border-bottom:1px solid rgba(15,23,42,.1);backdrop-filter:blur(16px)}.aio-doc-header-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:24px}.aio-doc-logo{display:flex;align-items:center;gap:12px;font-weight:900}.aio-doc-logo img{width:150px;height:48px;object-fit:contain}.aio-doc-nav{display:flex;gap:20px;overflow:auto;font-size:14px;font-weight:800}.aio-doc-actions{display:flex;align-items:center;gap:10px}.aio-doc-button{padding:10px 15px;border:0;border-radius:999px;background:var(--th-landing-accent);color:#fff;font-weight:800;cursor:pointer}.aio-doc-footer{margin-top:36px;padding:36px 0;background:#111827;color:#fff}.aio-doc-footer-grid{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}.aio-doc-footer p{color:#cbd5e1}@media(max-width:760px){.aio-doc-header-inner{padding:12px 0;flex-wrap:wrap}.aio-doc-nav{order:3;width:100%;padding-bottom:8px}}
        </style>
</x-storefront-head>
<body>
        @include('partials.storefront-language-switcher')
<header class="aio-doc-header"><div class="aio-doc-container aio-doc-header-inner">
    <a class="aio-doc-logo" href="{{ route('site.home') }}">@if(filled(data_get($documentBranding,'logo_url')))<img src="{{ data_get($documentBranding,'logo_url') }}" alt="{{ data_get($documentBranding,'company_name',$documentThemeKey) }}">@else<span>{{ data_get($documentBranding,'company_name',$documentThemeKey) }}</span>@endif</a>
    <nav class="aio-doc-nav">@foreach($documentMenu as $item)<a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] }}</a>@endforeach</nav>
    <div class="aio-doc-actions">@if(!empty($customerAuth['is_authenticated']))<a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>@else<button class="aio-doc-button" type="button" data-open-auth-modal="login">Đăng nhập</button>@endif</div>
</div></header>
<main class="aio-doc-container">@include('partials.configurable-landing-blocks')</main>
<footer class="aio-doc-footer"><div class="aio-doc-container aio-doc-footer-grid"><div><strong>{{ data_get($documentBranding,'company_name',$documentThemeKey) }}</strong><p>{{ data_get($documentBranding,'slogan','Nền tảng website và landing page linh hoạt.') }}</p></div><div><strong>{{ data_get($documentBranding,'support_hotline','1900 6760') }}</strong><p>{{ data_get($documentBranding,'support_email','support@example.com') }}</p></div></div></footer>
@includeIf($documentViewNamespace.'::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
</body>
</html>
