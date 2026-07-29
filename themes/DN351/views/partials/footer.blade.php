@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Meatlers')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '1900 9477'));
    $email = trim((string) ($branding['support_email'] ?? data_get($siteProfile ?? [], 'email', 'hello@meatlers.vn')));
    $address = trim((string) ($branding['support_location'] ?? data_get($siteProfile ?? [], 'address', '344 Huỳnh Tấn Phát, Quận 7, TP. Hồ Chí Minh')));
@endphp
<footer class="dn351-footer">
    <div class="dn351-container dn351-footer__grid">
        <section>
            <a class="dn351-footer__logo" href="{{ route('site.home') }}">
                @if($logo !== '')<img src="{{ $logo }}" alt="{{ $siteName }}">@else<i class="fa-solid fa-cow"></i><strong>{{ $siteName }}</strong>@endif
            </a>
            <p>@themeT('DN351.footer.about', 'Meatlers kết nối nguồn thực phẩm minh bạch với những bữa ăn tươi ngon, an toàn và giàu dinh dưỡng.')</p>
            <h4>@themeT('DN351.footer.hours', 'Giờ mở cửa')</h4>
            <p>Thứ Hai – Thứ Bảy: 08:00 – 17:00</p>
            <div class="dn351-social"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a></div>
        </section>
        <section>
            <h3>@themeT('DN351.footer.contact', 'Thông tin liên hệ')</h3>
            <p><strong>Số điện thoại</strong><br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">{{ $hotline }}</a></p>
            <p><strong>Email</strong><br><a href="mailto:{{ $email }}">{{ $email }}</a></p>
            <p><strong>Địa chỉ cửa hàng</strong><br>{{ $address }}</p>
        </section>
        <section>
            <h3>@themeT('DN351.footer.products', 'Danh mục sản phẩm')</h3>
            <a href="{{ route('site.catalog.search') }}?q=thịt">Các loại thịt</a>
            <a href="{{ route('site.catalog.search') }}?q=hải+sản">Hải sản</a>
            <a href="{{ route('site.catalog.search') }}?q=rau">Rau sạch</a>
            <a href="{{ route('site.catalog.search') }}?q=trái+cây">Trái cây</a>
        </section>
        <section>
            <h3>@themeT('DN351.footer.services', 'Danh mục dịch vụ')</h3>
            <a href="{{ route('site.services.index') }}">Giao nông sản – thực phẩm</a>
            <a href="{{ route('site.services.index') }}">Vận chuyển hàng hải sản</a>
            <a href="{{ route('site.contact') }}">Đổi trả hàng thực phẩm</a>
        </section>
    </div>
    <div class="dn351-footer__copy">© {{ now()->year }} {{ $siteName }} · @themeT('DN351.footer.rights', 'Bản quyền nội dung thuộc về Meatlers.')</div>
</footer>
