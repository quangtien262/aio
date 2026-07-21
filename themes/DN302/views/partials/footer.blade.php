@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Website')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $description = trim(strip_tags((string) ($branding['company_description'] ?? data_get($siteProfile ?? [], 'description', ''))));
    $address = trim((string) ($branding['support_location'] ?? data_get($siteProfile ?? [], 'address', '')));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? data_get($siteProfile ?? [], 'email', '')));
    $phoneHref = preg_replace('/[^0-9+]/', '', $hotline);

    $footerMenuItems = collect(data_get(
        $themeShellData ?? [],
        'top_menu',
        data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))
    ))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label', data_get($item, 'title'))))
        ->take(6)
        ->values();

    if ($footerMenuItems->isEmpty()) {
        $footerMenuItems = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
            ['label' => 'Dịch vụ', 'url' => route('site.home').'#dich-vu'],
            ['label' => 'Dự án', 'url' => route('site.home').'#du-an'],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }

    $socials = collect([
        ['label' => 'Facebook', 'icon' => 'fa-facebook-f', 'url' => data_get($branding, 'facebook_url', data_get($branding, 'socials.facebook'))],
        ['label' => 'YouTube', 'icon' => 'fa-youtube', 'url' => data_get($branding, 'youtube_url', data_get($branding, 'socials.youtube'))],
        ['label' => 'X', 'icon' => 'fa-x-twitter', 'url' => data_get($branding, 'x_url', data_get($branding, 'socials.x'))],
        ['label' => 'Pinterest', 'icon' => 'fa-pinterest-p', 'url' => data_get($branding, 'pinterest_url', data_get($branding, 'socials.pinterest'))],
    ])->filter(fn (array $social) => filled($social['url']));
@endphp
<footer id="footer" class="dn-footer">
    <div class="dn-footer-accent" aria-hidden="true"></div>
    <div class="dn-container dn-footer-main" data-dn-reveal="up">
        <section class="dn-footer-brand">
            <a class="dn-footer-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }} - Trang chủ">
                @if($logo !== '')
                    <img src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <i class="fa-regular fa-window-maximize"></i>
                @endif
                <span>{{ $siteName }}</span>
            </a>
            <p>
                @if($description !== '')
                    {{ $description }}
                @else
                    @themeT('DN302.footer.description', 'Giải pháp đồng bộ, tận tâm và bền vững cho mọi công trình.')
                @endif
            </p>
            @if($socials->isNotEmpty())
                <div class="dn-social-list" aria-label="Mạng xã hội">
                    @foreach($socials as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="fa-brands {{ $social['icon'] }}"></i></a>
                    @endforeach
                </div>
            @endif
        </section>

        <nav class="dn-footer-links" aria-label="@themeT('DN302.footer.quick_links', 'Liên kết nhanh')">
            <h3>@themeT('DN302.footer.quick_links', 'Liên kết nhanh')</h3>
            <ul>
                @foreach($footerMenuItems as $item)
                    <li><a href="{{ data_get($item, 'url', data_get($item, 'link_url', route('site.home'))) }}" target="{{ data_get($item, 'target', '_self') }}"><i class="fa-solid fa-arrow-right"></i>{{ data_get($item, 'label', data_get($item, 'title')) }}</a></li>
                @endforeach
            </ul>
        </nav>

        <section class="dn-footer-contact">
            <h3>@themeT('DN302.footer.contact', 'Thông tin liên hệ')</h3>
            <div class="dn-footer-contact-list">
                @if($address !== '')
                    <a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i><span><small>@themeT('DN302.footer.address', 'Địa chỉ')</small>{{ $address }}</span></a>
                @endif
                @if($hotline !== '')
                    <a href="tel:{{ $phoneHref }}"><i class="fa-solid fa-phone"></i><span><small>Hotline</small>{{ $hotline }}</span></a>
                @endif
                @if($email !== '')
                    <a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i><span><small>Email</small>{{ $email }}</span></a>
                @endif
            </div>
        </section>

        <aside class="dn-footer-cta">
            <span>@themeT('DN302.footer.cta_kicker', 'Bạn cần tư vấn?')</span>
            <h3>@themeT('DN302.footer.cta_title', 'Cùng chúng tôi hiện thực hóa không gian của bạn')</h3>
            <p>@themeT('DN302.footer.cta_description', 'Để lại nhu cầu, đội ngũ chuyên môn sẽ liên hệ và đề xuất giải pháp phù hợp.') </p>
            <button type="button" data-dn-consult-open>@themeT('DN302.footer.cta_button', 'Đăng ký tư vấn') <i class="fa-solid fa-arrow-right"></i></button>
        </aside>
    </div>
    <div class="dn-footer-copy">
        <div class="dn-container dn-footer-copy-inner">
            <span>© {{ now()->year }} {{ $siteName }}. @themeT('DN302.footer.rights', 'Bảo lưu mọi quyền.')</span>
            <span>@themeT('DN302.footer.copyright', 'Sản phẩm của HT Việt Nam')</span>
        </div>
    </div>
    <a class="dn-to-top" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-arrow-up"></i></a>
</footer>
