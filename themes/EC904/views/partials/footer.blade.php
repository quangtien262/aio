@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $brand = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($brand, 'logo_url', ''));
    $siteName = trim((string) data_get($brand, 'company_name', data_get($siteProfile ?? [], 'site_name', 'PocoMall'))) ?: 'PocoMall';
@endphp
<footer class="ec94-footer" id="newsletter"><div class="ec94-container ec94-footer-main">
    <section><a class="ec94-logo is-footer" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>POCO</span><b>Mall</b><small>THIÊN ĐƯỜNG MUA SẮM</small>@endif</a><h3>{{ $siteName }}</h3><p>{{ data_get($brand, 'company_description', 'Siêu thị đa ngành với sản phẩm chọn lọc và dịch vụ tận tâm.') }}</p><p>Địa chỉ: {{ data_get($brand, 'support_location', '266 Đội Cấn, Ba Đình, Hà Nội') }}<br>Điện thoại: {{ data_get($brand, 'support_hotline', '0399162342') }} · Email: {{ data_get($brand, 'support_email', 'support@pocomall.vn') }}</p></section>
    <section><h3>NHẬN TIN KHUYẾN MÃI</h3><form><input type="email" placeholder="Nhập email của bạn"><button>Đăng ký</button></form><div class="ec94-socials"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-google"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div></section>
</div><div class="ec94-container ec94-footer-bottom"><span>Bản quyền thuộc về <b>PocoMall Creative</b></span><nav><a href="{{ route('site.home') }}">@themeT('nav.home', 'Trang chủ')</a><a href="{{ route('site.catalog.search') }}">@themeT('nav.products', 'Sản phẩm')</a><a href="{{ route('site.blog.index') }}">@themeT('nav.latest_news', 'Tin mới nhất')</a><a href="{{ route('site.contact') }}">@themeT('nav.contact', 'Liên hệ')</a></nav></div></footer>
