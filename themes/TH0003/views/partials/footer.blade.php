@php
    $footerContainerClass = $footerContainerClass ?? 'th-container';
    $footerCompanyFirst = (bool) ($footerCompanyFirst ?? false);
    $footerShell = $footerShell ?? ($themeShellData ?? ($shell ?? ($homeData ?? [])));
    $footerBranding = $branding ?? ($footerShell['branding'] ?? []);
    $footerColumnsData = $footerColumns ?? ($footerShell['footer_columns'] ?? []);
    $footerCompanyData = $companyFooter ?? ($footerShell['company_footer'] ?? []);
    $footerCompanyName = $companyTitle ?? data_get($siteProfile ?? null, 'branding.company_name', data_get($footerBranding, 'company_name', 'TH0003 Fashion'));
    $footerHotline = $contactHotline ?? data_get($footerBranding, 'support_hotline', '1900 6760 / 0354.466.968');
    $footerEmail = $contactEmail ?? data_get($footerBranding, 'support_email', 'cs@TH0003.demo');
    $staticPageUrl = fn (string $slug): string => route('site.pages.show', ['slug' => $slug]);

    if ($footerColumnsData === []) {
        $footerColumnsData = [
            [
                'title' => 'Hỗ trợ mua hàng',
                'links' => [
                    ['label' => 'Chính sách giao hàng', 'url' => $staticPageUrl('chinh-sach-giao-hang')],
                    ['label' => 'Đổi trả và bảo hành', 'url' => $staticPageUrl('doi-tra-va-bao-hanh')],
                    ['label' => 'Hướng dẫn chọn size', 'url' => $staticPageUrl('huong-dan-chon-size')],
                    ['label' => 'Theo dõi đơn hàng', 'url' => route('customer.account')],
                ],
            ],
            [
                'title' => 'Về thương hiệu',
                'links' => [
                    ['label' => 'Về chúng tôi', 'url' => $staticPageUrl('gioi-thieu')],
                    ['label' => 'Liên hệ showroom', 'url' => route('site.contact')],
                    ['label' => 'Chính sách bảo mật', 'url' => $staticPageUrl('chinh-sach-bao-mat')],
                    ['label' => 'Fashion journal', 'url' => route('site.blog.index')],
                ],
            ],
            [
                'title' => 'Bộ sưu tập',
                'links' => [
                    ['label' => 'Hàng mới', 'url' => route('site.catalog.search')],
                    ['label' => 'Sản phẩm nổi bật', 'url' => route('site.catalog.search')],
                    ['label' => 'Danh mục sản phẩm', 'url' => route('site.catalog.search')],
                    ['label' => 'Tin tức phối đồ', 'url' => route('site.blog.index')],
                ],
            ],
        ];
    }

    $normalizedFooterColumns = collect($footerColumnsData)
        ->map(function (mixed $column, mixed $key): ?array {
            if (is_array($column) && array_key_exists('title', $column)) {
                $links = collect($column['links'] ?? [])
                    ->map(fn (mixed $link): array => is_array($link)
                        ? [
                            'label' => (string) ($link['label'] ?? ''),
                            'url' => (string) ($link['url'] ?? route('site.home')),
                            'target' => (string) ($link['target'] ?? '_self'),
                        ]
                        : [
                            'label' => (string) $link,
                            'url' => route('site.home'),
                            'target' => '_self',
                        ])
                    ->filter(fn (array $link): bool => $link['label'] !== '')
                    ->values()
                    ->all();

                return [
                    'title' => (string) ($column['title'] ?? ''),
                    'links' => $links,
                ];
            }

            if (is_array($column)) {
                $links = collect($column)
                    ->map(fn (mixed $link): array => is_array($link)
                        ? [
                            'label' => (string) ($link['label'] ?? ''),
                            'url' => (string) ($link['url'] ?? route('site.home')),
                            'target' => (string) ($link['target'] ?? '_self'),
                        ]
                        : [
                            'label' => (string) $link,
                            'url' => route('site.home'),
                            'target' => '_self',
                        ])
                    ->filter(fn (array $link): bool => $link['label'] !== '')
                    ->values()
                    ->all();

                return [
                    'title' => is_string($key) ? $key : '',
                    'links' => $links,
                ];
            }

            return null;
        })
        ->filter(fn (?array $column): bool => is_array($column) && ($column['title'] !== '' || $column['links'] !== []))
        ->values();

    $footerCompanyLineOne = $footerCompanyData['address_line_1'] ?? 'Showroom TH0003: 332 Lũy Bán Bích, Tân Phú, TP.HCM';
    $footerCompanyLineTwo = $footerCompanyData['address_line_2'] ?? 'Chi nhánh Hà Nội: Thanh Xuân - nhận tư vấn size, đổi trả và pickup tại cửa hàng';
@endphp

<footer class="th-footer">
    <div class="{{ $footerContainerClass }} th-footer-inner">
        <div class="th-footer-grid">
            @if ($footerCompanyFirst)
                <section class="th-company">
                    <strong>{{ mb_strtoupper((string) $footerCompanyName, 'UTF-8') }}</strong>
                    <div class="th-footer-links">
                        <span>{{ $footerCompanyLineOne }}</span>
                        <span>{{ $footerCompanyLineTwo }}</span>
                        <span>Hotline: {{ $footerHotline }}</span>
                        <span>Email: {{ $footerEmail }}</span>
                    </div>
                    @include('partials.boc-footer-status', ['branding' => $footerBranding, 'class' => 'th-footer-boc-status'])
                </section>
            @endif

            @foreach ($normalizedFooterColumns as $column)
                <section class="th-footer-card">
                    <h4>{{ $column['title'] }}</h4>
                    <div class="th-footer-links">
                        @foreach ($column['links'] as $link)
                            <a href="{{ $link['url'] }}" target="{{ $link['target'] }}">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach

            @unless ($footerCompanyFirst)
                <section class="th-company">
                    <strong>{{ mb_strtoupper((string) $footerCompanyName, 'UTF-8') }}</strong>
                    <div class="th-footer-links">
                        <span>{{ $footerCompanyLineOne }}</span>
                        <span>{{ $footerCompanyLineTwo }}</span>
                        <span>Hotline: {{ $footerHotline }}</span>
                        <span>Email: {{ $footerEmail }}</span>
                    </div>
                    @include('partials.boc-footer-status', ['branding' => $footerBranding, 'class' => 'th-footer-boc-status'])
                </section>
            @endunless
        </div>
    </div>
</footer>
