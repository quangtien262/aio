@php
    $profile = $siteProfile ?? [];
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'Sudes Phone'))) ?: 'Sudes Phone';
    $phone = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $location = trim((string) data_get($branding, 'support_location', ''));
    $description = trim((string) data_get($branding, 'company_description', '')) ?: 'Hệ thống bán lẻ điện thoại, máy tính, đồng hồ thông minh và phụ kiện chính hãng.';
@endphp
<footer class="ec12-footer">
    <div class="ec12-container ec12-footer-grid">
        <section>
            <a class="ec12-logo ec12-footer-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif
            </a>
            <p>{{ $description }}</p>
            <p><b>Địa chỉ:</b> {{ $location }}</p>
            <p><b>Điện thoại:</b> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></p>
            <p><b>Email:</b> <a href="mailto:{{ $email }}">{{ $email }}</a></p>
        </section>
        <section>
            <h3>@themeT('EC912.footer_policies', 'CHÍNH SÁCH')</h3>
            <a href="#">Chính sách mua hàng</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách bảo mật</a><a href="#">Cam kết cửa hàng</a>
        </section>
        <section>
            <h3>@themeT('EC912.footer_guides', 'HƯỚNG DẪN')</h3>
            <a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn đổi trả</a><a href="#">Hướng dẫn chuyển khoản</a><a href="#">Hướng dẫn trả góp</a><a href="#">Hướng dẫn hoàn hàng</a>
        </section>
        <section>
            <h3>@themeT('EC912.footer_connect', 'KẾT NỐI VỚI CHÚNG TÔI')</h3>
            <div class="ec12-social"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-instagram"></i><i class="fa-solid fa-bag-shopping"></i><i class="fa-solid fa-heart"></i><i class="fa-brands fa-tiktok"></i></div>
            <h3>@themeT('EC912.footer_payment', 'HỖ TRỢ THANH TOÁN')</h3>
            <div class="ec12-payments"><b>AlePay</b><b>ZaloPay</b><b>VNPAY</b><b>MOCA</b><b>OnePay</b><b>ATM</b></div>
        </section>
    </div>
</footer>
