@php
    $xd5Branding = (array) data_get($themeShellData ?? $shell ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $xd5CompanyName = trim((string) ($companyName ?? $logoAlt ?? $xd5Branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $xd5CompanyDescription = trim((string) ($companyDescription ?? $xd5Branding['company_description'] ?? 'Giải pháp tư vấn chuyên nghiệp, đồng hành cùng doanh nghiệp phát triển bền vững.'));
    $xd5SupportAddress = trim((string) ($supportAddress ?? $address ?? $xd5Branding['support_location'] ?? ''));
    $xd5SupportEmail = trim((string) ($supportEmail ?? $email ?? $xd5Branding['support_email'] ?? ''));
    $xd5Hotline = trim((string) ($hotline ?? $xd5Branding['support_hotline'] ?? ''));
    $xd5LogoUrl = trim((string) ($logoUrl ?? $xd5Branding['logo_url'] ?? ''));
@endphp

<footer id="footer" class="xd5-footer">
    <div class="xd5-container">
        <div class="xd5-footer-top">
            <a class="xd5-brand" href="#top">@if(filled($xd5LogoUrl))<img src="{{ $xd5LogoUrl }}" alt="{{ $xd5CompanyName }}">@endif</a>
            @if(filled($xd5Hotline))<div><small>Đặt câu hỏi</small><b>{{ $xd5Hotline }}</b></div>@endif
            @if(filled($xd5SupportEmail))<div><small>Gửi email</small><b>{{ $xd5SupportEmail }}</b></div>@endif
        </div>
        <div class="xd5-footer-grid">
            <div><p>{{ $xd5SupportAddress }}</p><p>{{ $xd5CompanyDescription }}</p></div>
            <div><h3>Khám phá</h3><ul>@foreach($navItems ?? [] as $item)<li><a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Liên kết' }}</a></li>@endforeach</ul></div>
            <div><h3>Đăng ký nhận tin</h3><form><input type="email" placeholder="Địa chỉ email"><button>Gửi</button></form></div>
        </div>
    </div>
</footer>
