@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'ND Interior'))) ?: 'ND Interior';
    $phone = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $location = trim((string) data_get($branding, 'support_location', ''));
@endphp
<footer class="ec15-footer">
    <div class="ec15-container ec15-footer-grid">
        <section><a class="ec15-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif</a><p>Thiết kế, thi công và cung cấp sản phẩm nội thất cao cấp cho không gian sống hiện đại.</p><div class="ec15-contact"><p><i class="fa-solid fa-location-dot"></i>{{ $location }}</p><p><i class="fa-solid fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p><p><i class="fa-solid fa-phone"></i><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></p></div></section>
        <section><h3>Về chúng tôi</h3><a href="#gioi-thieu">Giới thiệu</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.blog.index') }}">Tin tức</a><a href="{{ route('site.catalog.search') }}">Sản phẩm</a></section>
        <section><h3>Dịch vụ khách hàng</h3><a href="#">Kiểm tra đơn hàng</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách đổi trả</a><a href="#">Bảo mật khách hàng</a><a href="#">Đăng ký tài khoản</a></section>
        <section><h3>Theo dõi dự án</h3><div class="ec15-instagram">@foreach(['room-living-room.webp','room-bedroom.webp','room-office.webp','room-dining-room.webp','hero-interior.webp','contact-bedroom.webp'] as $image)<img src="/theme-demo/ec915/{{ $image }}" alt="Dự án nội thất">@endforeach</div></section>
    </div>
    <div class="ec15-copyright">© {{ date('Y') }} {{ $siteName }}. Thiết kế cho trải nghiệm sống khác biệt.</div>
</footer>
