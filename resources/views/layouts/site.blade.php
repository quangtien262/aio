<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <x-storefront-head
    :site-profile="$siteProfile ?? null"
    :theme-shell-data="$themeShellData ?? []"
    :active-theme="$activeTheme ?? null"
    :landing-page="$landingPage ?? null"
    :page-title="$pageTitle ?? null"
    :page-description="$pageDescription ?? null"
    :page-keywords="$pageKeywords ?? null"
    :canonical-url="$canonicalUrl ?? null"
    :hreflang-urls="$hreflangUrls ?? []"
    :is-preview="$isPreview ?? false"
>
    @vite('resources/css/app.css')
            <style>
                body {
                    margin: 0;
                    font-family: 'Segoe UI', sans-serif;
                    background:
                        radial-gradient(circle at top left, rgba(15, 118, 110, 0.18), transparent 28%),
                        linear-gradient(180deg, #f4fbf8 0%, #ffffff 100%);
                    color: #16302b;
                }

                .shell {
                    min-height: 100vh;
                    display: grid;
                    place-items: center;
                    padding: 32px;
                }

                .panel {
                    width: min(960px, 100%);
                    background: rgba(255, 255, 255, 0.86);
                    border: 1px solid #dbe7e4;
                    border-radius: 24px;
                    padding: 32px;
                    box-shadow: 0 30px 90px rgba(22, 48, 43, 0.08);
                    backdrop-filter: blur(14px);
                }

                .kicker {
                    text-transform: uppercase;
                    letter-spacing: 0.14em;
                    font-size: 12px;
                    color: #0f766e;
                    margin-bottom: 10px;
                }

                h1 {
                    font-size: clamp(32px, 5vw, 56px);
                    line-height: 1.05;
                    margin: 0 0 16px;
                }

                p {
                    font-size: 18px;
                    line-height: 1.7;
                    color: #46635c;
                }

                .actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 14px;
                    margin-top: 28px;
                }

                .button {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 14px 18px;
                    border-radius: 14px;
                    text-decoration: none;
                    font-weight: 600;
                }

                .button-primary {
                    background: #0f766e;
                    color: #fff;
                }

                .button-secondary {
                    background: #edf6f3;
                    color: #16302b;
                }

                .grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 16px;
                    margin-top: 28px;
                }

                .card {
                    padding: 18px;
                    border-radius: 18px;
                    background: #f8fbfa;
                    border: 1px solid #dbe7e4;
                }

                .card strong {
                    display: block;
                    margin-bottom: 8px;
                }
            </style>
</x-storefront-head>
    <body>
        @php
            $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
            $companyName = data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', config('app.name', 'AIO Platform')));
            $logo = data_get($branding, 'logo_url');
            $hotline = data_get($branding, 'support_hotline');
            $email = data_get($branding, 'support_email');
            $address = data_get($branding, 'support_location');
            $headerMenu = collect(data_get($themeShellData ?? [], 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
                ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))
                ->values();
        @endphp
        <header style="display:flex;align-items:center;justify-content:space-between;gap:24px;padding:18px 32px;background:#fff;border-bottom:1px solid #dbe7e4">
            <a href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if(filled($logo))
                    <img src="{{ $logo }}" alt="{{ $companyName }}" style="max-height:56px;max-width:240px">
                @else
                    <strong>{{ $companyName }}</strong>
                @endif
            </a>
            @if($headerMenu->isNotEmpty())
                <nav style="display:flex;align-items:center;flex-wrap:wrap;gap:18px">
                    @foreach($headerMenu as $item)
                        <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}" style="color:#16302b;text-decoration:none;font-weight:600">{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @endif
        @include('partials.storefront-language-switcher')
</header>

@yield('content')
        <footer style="padding:24px 32px;background:#16302b;color:#fff">@include('themes.common.footer-logo')<br>
            <strong>{{ $companyName }}</strong>
            <p style="color:#fff">{{ $address }}</p>
            <p style="color:#fff">
                <a style="color:#fff" href="tel:{{ preg_replace('/\D+/', '', (string) $hotline) }}">{{ $hotline }}</a>
                · <a style="color:#fff" href="mailto:{{ $email }}">{{ $email }}</a>
            </p>
        </footer>
    </body>
</html>
