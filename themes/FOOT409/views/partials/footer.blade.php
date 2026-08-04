@php
    $shell=$themeShellData??$themeHomeData??[];
    $branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
    $siteName=trim((string)data_get($siteProfile??[],'site_name','FOOT409'));
    $logo=trim((string)data_get($branding,'logo_url'));
    $hotline=trim((string)data_get($branding,'support_hotline'));
    $email=trim((string)data_get($branding,'support_email'));
    $address=trim((string)data_get($branding,'support_location'));
    $company=trim((string)data_get($branding,'company_name',$siteName));
    $description=trim((string)data_get($branding,'company_description','Chuỗi cửa hàng đồ ăn nhanh với thực đơn đa dạng, phục vụ nhanh chóng và tiện lợi.'));
    $tax=trim((string)data_get($branding,'tax_code'));
    $copyright=trim((string)data_get($branding,'copyright_text'));
@endphp
<footer class="f409-footer" id="footer">
    <div class="f409-container f409-footer__grid">
        <section>
            <a class="f409-logo f409-footer__logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><i class="fa-solid fa-utensils"></i></span><strong>{{ $siteName }}</strong>@endif</a>
            <h3>{{ $company }}</h3><p>{{ $description }}</p>
            @if($tax)<p>Mã số thuế: {{ $tax }}</p>@endif
            @if($address)<p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p>@endif
            <div class="f409-contact">@if($hotline)<a href="tel:{{ preg_replace('/\s+/','',$hotline) }}"><small>Hotline</small><strong>{{ $hotline }}</strong></a>@endif @if($email)<a href="mailto:{{ $email }}"><small>Email</small><strong>{{ $email }}</strong></a>@endif</div>
        </section>
        <section><h3>Hỗ trợ khách hàng</h3><a href="{{ route('site.contact') }}">Câu hỏi thường gặp</a><a href="{{ route('site.home') }}#cua-hang">Hệ thống cửa hàng</a><a href="{{ route('site.catalog.search') }}">Tìm kiếm</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.blog.index') }}">Tin tức</a></section>
        <section><h3>Chính sách</h3><a href="#">Chính sách đổi trả</a><a href="#">Chính sách bảo mật</a><a href="#">Điều khoản dịch vụ</a><h3>Tổng đài hỗ trợ</h3>@if($hotline)<p>Gọi mua hàng: <b>{{ $hotline }}</b></p>@endif</section>
        <section><h3>Đăng ký nhận ưu đãi</h3><p>Nhận khuyến mãi đặc biệt và món mới ngay khi ra mắt.</p><form class="f409-newsletter"><input type="email" placeholder="Email của bạn..."><button type="button">Đăng ký</button></form><h3>Phương thức thanh toán</h3><div class="f409-payments"><b>VISA</b><b>Mastercard</b><b>MoMo</b><b>ZaloPay</b></div></section>
    </div>
    <div class="f409-copyright">{{ $copyright!==''?$copyright:'© '.now()->year.' '.$siteName.'. All rights reserved.' }}</div>
</footer>
