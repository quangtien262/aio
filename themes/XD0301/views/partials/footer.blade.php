@php
    $footerBranding = (array) data_get($themeShellData ?? [], 'branding',
        data_get($shell ?? [], 'branding',
        data_get($themeHomeData ?? [], 'branding',
        data_get($siteProfile ?? [], 'branding', []))));

    $footerCompanyName = trim((string) ($footerBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $footerDescription = trim((string) ($footerBranding['company_description'] ?? data_get($siteProfile ?? [], 'description', '')));
    $footerDescription = $footerDescription !== ''
        ? $footerDescription
        : $footerCompanyName.' là đơn vị tư vấn, cung cấp và thi công giải pháp cho công trình hiện đại.';
    $footerLogoUrl = trim((string) ($footerBranding['logo_url'] ?? ($logoUrl ?? '')));
    $footerLogoAlt = trim((string) ($footerBranding['logo_alt'] ?? $footerBranding['company_name'] ?? ($logoAlt ?? $footerCompanyName))) ?: $footerCompanyName;
    $footerHotline = trim((string) ($footerBranding['support_hotline'] ?? ($hotline ?? '0399162342'))) ?: '0399162342';
    $footerPhoneHref = preg_replace('/\D+/', '', $footerHotline) ?: $footerHotline;
    $footerEmail = trim((string) ($footerBranding['support_email'] ?? $footerBranding['email'] ?? ($email ?? 'admin@htvietnam.vn'))) ?: 'admin@htvietnam.vn';
    $footerAddress = trim((string) ($footerBranding['support_location'] ?? $footerBranding['address'] ?? ($address ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM'))) ?: '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM';
    $footerSource = $footerNewsletterSource ?? 'theme-footer-xd0301';

    $footerUrlResolver = function (?string $href): string {
        $href = trim((string) $href);

        if ($href === '') {
            return '#';
        }

        if ($href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href;
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return route('site.home').$query.$fragment;
        }

        $segments = explode('/', $path);
        $knownLocales = \App\Support\FrontendLocalization::knownLocaleCodes();

        if (! in_array($segments[0] ?? '', $knownLocales, true)) {
            array_unshift($segments, app()->getLocale());
        }

        return url('/'.implode('/', $segments)).$query.$fragment;
    };

    $footerMenuItems = collect(data_get($menus ?? [], 'footer', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $footerUrlResolver((string) ($item['url'] ?? $item['href'] ?? '#')),
            'target' => $item['target'] ?? '_self',
        ])
        ->values();

    if ($footerMenuItems->isEmpty()) {
        $footerMenuItems = collect([
            ['label' => 'Trang chủ', 'href' => route('site.home'), 'target' => '_self'],
            ['label' => 'Tin tức', 'href' => route('site.blog.index'), 'target' => '_self'],
            ['label' => 'Giới thiệu', 'href' => url('/'.app()->getLocale().'/gioi-thieu'), 'target' => '_self'],
            ['label' => 'Liên hệ', 'href' => route('site.contact'), 'target' => '_self'],
        ]);
    }
@endphp

<footer id="lien-he" class="xd-footer">
    <div class="xd-container xd-footer-grid">
        <div>
            <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $footerCompanyName }}">
                @if ($footerLogoUrl !== '')
                    <img class="xd-logo-image" src="{{ $footerLogoUrl }}" alt="{{ $footerLogoAlt }}">
                @else
                    <i class="xd-logo-mark" aria-hidden="true"></i><b>{{ $footerCompanyName }}</b>
                @endif
            </a>
            <p>{!! nl2br(e($footerDescription)) !!}</p>
        </div>
        <div>
            <h3>Thông tin</h3>
            <nav class="xd-footer-links" aria-label="Thông tin">
                @foreach ($footerMenuItems as $item)
                    <a href="{{ $item['href'] }}" target="{{ $item['target'] }}" @if (($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
        <div>
            <h3>Liên hệ</h3>
            <div class="xd-contact-list">
                <a href="https://maps.google.com/?q={{ urlencode($footerAddress) }}" target="_blank" rel="noopener noreferrer">&#128205; {{ $footerAddress }}</a>
                <a href="mailto:{{ $footerEmail }}">&#9993; {{ $footerEmail }}</a>
                <a href="tel:{{ $footerPhoneHref }}">&#9742; {{ $footerHotline }}</a>
            </div>
            <div class="xd-footer-newsletter">
                <h4>Đăng ký nhận tin</h4>
                <p>Đăng ký email để nhận thông tin mới nhất từ chúng tôi</p>
            </div>
            <form class="xd-newsletter" method="POST" action="{{ route('site.newsletter.subscribe') }}">
                @csrf
                <input type="hidden" name="source" value="{{ $footerSource }}">
                <input type="email" name="email" placeholder="Địa chỉ email....." required>
                <button type="submit">Đăng ký</button>
            </form>
            @if (session('newsletter_success'))
                <p class="xd-newsletter-note is-success">{{ session('newsletter_success') }}</p>
            @endif
            @if ($errors->newsletter->first('email'))
                <p class="xd-newsletter-note is-error">{{ $errors->newsletter->first('email') }}</p>
            @endif
        </div>
    </div>
</footer>
