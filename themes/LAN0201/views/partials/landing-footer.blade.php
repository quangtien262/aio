@php
    $rawFooterColumns = $footerColumns ?? [];
    $footerColumns = collect($rawFooterColumns)->mapWithKeys(function ($value, $key): array {
        if (is_array($value) && array_key_exists('title', $value)) {
            return [(string) ($value['title'] ?? 'Thông tin') => $value['links'] ?? []];
        }

        return [(string) $key => is_array($value) ? $value : []];
    })->all();
    $companyName = data_get($branding ?? [], 'company_name', data_get($siteProfile ?? null, 'site_name', 'LAN0201 Project Landing'));
    $supportLocation = data_get($branding ?? [], 'support_location', 'Sales gallery dự án');
    $supportHotline = data_get($branding ?? [], 'support_hotline', '1900 6760');
    $supportEmail = data_get($branding ?? [], 'support_email', 'sales@lan0201.demo');
@endphp
<footer class="th-landing-footer">
    <div class="th-landing-container th-landing-footer-inner">
        <div class="th-landing-footer-grid">
            <div class="th-landing-footer-card th-landing-company">
                <strong>{{ $companyName }}</strong>
                <div class="th-landing-copy">Landing page dự án mở bán, kết hợp bảng hàng, tin dự án và form lead trong cùng một shell riêng cho LAN0201.</div>
                <div class="th-landing-meta-row" style="margin-top:14px;">
                    <span class="th-landing-chip">{{ $supportLocation }}</span>
                    <span class="th-landing-chip">{{ $supportHotline }}</span>
                    <span class="th-landing-chip">{{ $supportEmail }}</span>
                </div>
            </div>
            @foreach ($footerColumns as $title => $links)
                <div class="th-landing-footer-card">
                    <h4>{{ $title }}</h4>
                    <div class="th-landing-footer-links">
                        @foreach ($links as $link)
                            <span>{{ $link }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</footer>