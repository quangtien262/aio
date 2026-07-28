@php($shell=$themeShellData??$themeHomeData??[])
@php($branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[])))
@php($logo=trim((string)data_get($branding,'logo_url','')))
@php($siteName=trim((string)data_get($branding,'company_name',data_get($siteProfile??[],'site_name','EGA Gear')))?:'EGA Gear')
@php($phone=data_get($branding,'support_hotline','1900 6750'))
@php($email=data_get($branding,'support_email','support@egagear.vn'))
<footer class="ec97-footer"><div class="ec97-container ec97-footer-grid">
<section><a class="ec97-logo is-footer" href="#top" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><i>E</i><b>EGA</b><strong>GEAR</strong></span>@endif</a><p>Chuyên cung cấp các thiết bị điện máy, điện tử và Gaming Gear.</p><p>Mã số thuế: 12345678910</p><p><i class="fa-solid fa-location-dot"></i> 70 Lữ Gia, Quận 11, TP. Hồ Chí Minh</p><div class="ec97-contact"><a href="tel:{{ preg_replace('/\s+/','',$phone) }}">Hotline<b>{{ $phone }}</b></a><a href="mailto:{{ $email }}">Email<b>{{ $email }}</b></a></div><h4>Mạng xã hội</h4><div class="ec97-social"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-tiktok"></i><i class="fa-brands fa-instagram"></i></div></section>
<section><h3>Hỗ trợ khách hàng</h3><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Câu hỏi thường gặp</a><a href="{{ route('site.catalog.search') }}">Kiểm tra đơn hàng</a><a href="{{ route('site.contact') }}">Liên hệ</a></section>
<section><h3>Chính sách</h3><a href="#">Chính sách bảo hành</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách bảo mật</a><a href="#">Chính sách trả góp</a><h3>Tổng đài hỗ trợ</h3><p>Gọi mua hàng: {{ $phone }} (8h–20h)</p><p>Gọi bảo hành: {{ $phone }} (8h–20h)</p></section>
<section id="newsletter"><h3>Đăng ký nhận ưu đãi</h3><p>Đăng ký để cập nhật khuyến mãi công nghệ ngay lập tức.</p><form action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Email của bạn..." aria-label="Email nhận ưu đãi" required><button type="submit">Đăng ký</button></form><h3>PHƯƠNG THỨC THANH TOÁN</h3><div class="ec97-pay"><b>VISA</b><b>MasterCard</b><b>momo</b><b>ZaloPay</b></div></section>
</div></footer>
