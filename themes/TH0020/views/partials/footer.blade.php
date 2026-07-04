@php
    $footerContainerClass = $footerContainerClass ?? 'th-container';
    $footerCompanyFirst = (bool) ($footerCompanyFirst ?? false);
    $footerShell = $themeShellData ?? ($shell ?? ($homeData ?? []));
    $footerBranding = $branding ?? ($footerShell['branding'] ?? []);
    $footerColumnsData = $footerColumns ?? ($footerShell['footer_columns'] ?? []);
    $footerCompanyData = $companyFooter ?? ($footerShell['company_footer'] ?? []);
    $footerCompanyName = $companyTitle ?? data_get($siteProfile ?? null, 'branding.company_name', data_get($footerBranding, 'company_name', 'TH0020 Living Studio'));
    $footerHotline = $contactHotline ?? data_get($footerBranding, 'support_hotline', '1900 6760 / 0902.020.020');
    $footerEmail = $contactEmail ?? data_get($footerBranding, 'support_email', 'studio@th0020.demo');
    $staticPageUrl = fn (string $slug): string => url('/'.app()->getLocale().'/'.$slug);

    if ($footerColumnsData === []) {
        $footerColumnsData = [
            [
                'title' => 'Ho tro mua hang',
                'links' => [
                    ['label' => 'Chinh sach giao lap', 'url' => $staticPageUrl('chinh-sach-giao-hang')],
                    ['label' => 'Doi tra va bao hanh', 'url' => $staticPageUrl('doi-tra-va-bao-hanh')],
                    ['label' => 'Huong dan do kich thuoc', 'url' => $staticPageUrl('huong-dan-chon-size')],
                    ['label' => 'Theo doi don hang', 'url' => route('customer.account')],
                ],
            ],
            [
                'title' => 'Ve showroom',
                'links' => [
                    ['label' => 'Ve chung toi', 'url' => $staticPageUrl('gioi-thieu')],
                    ['label' => 'Lien he showroom', 'url' => route('site.contact')],
                    ['label' => 'Chinh sach bao mat', 'url' => $staticPageUrl('chinh-sach-bao-mat')],
                    ['label' => 'Interior journal', 'url' => route('site.blog.index')],
                ],
            ],
            [
                'title' => 'Bo suu tap',
                'links' => [
                    ['label' => 'Phong khach', 'url' => route('site.catalog.search', ['q' => 'phong khach'])],
                    ['label' => 'Phong ngu', 'url' => route('site.catalog.search', ['q' => 'phong ngu'])],
                    ['label' => 'Decor va den', 'url' => route('site.catalog.search', ['q' => 'den trang tri'])],
                    ['label' => 'Tin y tuong', 'url' => route('site.blog.index')],
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

    $footerCompanyLineOne = $footerCompanyData['address_line_1'] ?? 'Showroom TH0020: 42 Nguyen Co Thach, Nam Tu Liem, Ha Noi';
    $footerCompanyLineTwo = $footerCompanyData['address_line_2'] ?? 'Experience studio TP.HCM: tu van phoi phong, vat lieu va giao lap theo lich hen';
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
                </section>
            @endunless
        </div>
    </div>
</footer>
