@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'SER102 Auto Detailing');
    $hotline = data_get($branding, 'support_hotline', '');
    $email = data_get($branding, 'support_email', data_get($siteProfile ?? [], 'support_email', ''));
    $address = data_get($branding, 'support_location', '');
@endphp
<footer class="ser102-footer">
    <div class="ser102-container">
        <div class="ser102-assurances">
            <div><i class="fa-solid fa-award"></i><span><strong>Sản phẩm chính hãng</strong><small>Nguồn gốc rõ ràng</small></span></div>
            <div><i class="fa-solid fa-headset"></i><span><strong>Dịch vụ chuyên nghiệp</strong><small>Đội ngũ giàu kinh nghiệm</small></span></div>
            <div><i class="fa-solid fa-shield-halved"></i><span><strong>Bảo hành uy tín</strong><small>Chính sách minh bạch</small></span></div>
            <div><i class="fa-solid fa-truck-fast"></i><span><strong>Phục vụ tận tâm</strong><small>Tiếp nhận nhanh chóng</small></span></div>
        </div>
        <div class="ser102-footer__grid">
            <section class="ser102-footer__brand">
                <a class="ser102-brand is-footer" href="{{ route('site.home') }}"><span class="ser102-brand__mark"><i class="fa-solid fa-car-side"></i></span><span><strong>{{ $companyName }}</strong><small>@themeT('SER102.brand.tagline')</small></span></a>
                <p>@themeT('SER102.footer.intro')</p>
                <a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i> {{ $email }}</a>
                <span><i class="fa-solid fa-location-dot"></i> {{ $address }}</span>
                <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> {{ $hotline }}</a>
            </section>
            <section><h3>@themeT('SER102.footer.services')</h3><a href="{{ route('site.services.index') }}">Chăm sóc ngoại thất</a><a href="{{ route('site.services.index') }}">Chăm sóc nội thất</a><a href="{{ route('site.services.index') }}">Phủ ceramic</a><a href="{{ route('site.services.index') }}">Hiệu chỉnh sơn</a></section>
            <section><h3>@themeT('SER102.nav.products')</h3><a href="{{ route('site.catalog.search') }}">Dung dịch chăm sóc</a><a href="{{ route('site.catalog.search') }}">Phụ kiện detailing</a><a href="{{ route('site.catalog.search') }}">Khăn và dụng cụ</a><a href="{{ route('site.cart.index') }}">Giỏ hàng</a></section>
            <section><h3>@themeT('SER102.footer.support')</h3><a href="{{ route('site.contact') }}">Chính sách bảo hành</a><a href="{{ route('site.blog.index') }}">Kiến thức chăm xe</a><button type="button" data-ser102-booking-open>Đặt lịch dịch vụ</button><div class="ser102-socials"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a></div></section>
        </div>
        <div class="ser102-footer__bottom">© {{ now()->year }} {{ $companyName }}. @themeT('SER102.footer.copyright')</div>
    </div>
</footer>
