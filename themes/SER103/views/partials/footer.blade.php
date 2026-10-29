@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'Bøhu.');
    $hotline = data_get($branding, 'support_hotline', '');
    $email = data_get($branding, 'support_email', '');
    $address = data_get($branding, 'support_location', '');
    $footerServices = data_get($themeShellData ?? $themeHomeData ?? [], 'footer_services', []);
    $socialLinks = collect([
        ['key' => 'facebook_url', 'label' => 'Facebook', 'icon' => 'fa-facebook-f'],
        ['key' => 'x_url', 'label' => 'X', 'icon' => 'fa-x-twitter'],
        ['key' => 'youtube_url', 'label' => 'YouTube', 'icon' => 'fa-youtube'],
        ['key' => 'pinterest_url', 'label' => 'Pinterest', 'icon' => 'fa-pinterest-p'],
    ])->map(fn ($social) => array_merge($social, ['url' => trim((string) data_get($branding, $social['key'], ''))]))
        ->filter(fn ($social) => filter_var($social['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($social['url'], PHP_URL_SCHEME)), ['http', 'https'], true));
@endphp
<footer class="ser103-footer">
    <div class="ser103-container">
        <div class="ser103-footer__grid">
            <section class="ser103-footer__brand">
                @include('themes.common.footer-logo')
                <p>{{ data_get($branding, 'company_description', 'Studio cưới đồng hành cùng các cặp đôi viết nên câu chuyện tình yêu của riêng mình bằng trải nghiệm tinh tế và đáng nhớ.') }}</p>
                <p>{{ $address }} @if(filled($hotline)) · <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a>@endif @if(filled($email)) · <a href="mailto:{{ $email }}">{{ $email }}</a>@endif</p>
                @if($socialLinks->isNotEmpty())<div class="ser103-socials">@foreach($socialLinks as $social)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="fa-brands {{ $social['icon'] }}"></i></a>@endforeach</div>@endif
            </section>
            <section><h3>Danh mục dịch vụ</h3>@foreach($footerServices as $service)<a href="{{ $service['url'] }}">{{ $service['title'] }}</a>@endforeach<a href="{{ route('site.services.index') }}">Tất cả dịch vụ</a></section>
            <section><h3>Thông tin & hỗ trợ</h3><a href="{{ route('site.home') }}">Trang chủ</a><a href="{{ route('site.blog.index') }}">Cẩm nang cưới</a><a href="{{ route('site.contact') }}">Liên hệ tư vấn</a></section>
            <section><h3>Đăng ký nhận tin</h3><p>Đăng ký nhận bản tin hàng tuần để nhận thông tin cập nhật mới nhất.</p><form class="ser103-newsletter" action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Địa chỉ email..." required><button type="submit"><i class="fa-regular fa-paper-plane"></i> Đăng ký</button></form></section>
        </div>
        <div class="ser103-footer__bottom">© {{ now()->year }} {{ $companyName }}. All rights reserved.</div>
    </div>
</footer>
