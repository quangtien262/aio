@php
$shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$phone=$branding['support_hotline']??'';$email=$branding['support_email']??'';$address=$branding['support_location']??'';$name=trim((string)($branding['company_name']??''))?:'Sudes Aquarium';$logo=trim((string)($branding['logo_url']??''));
@endphp
<footer id="lien-he" class="ca50-footer xd-landing-block" data-landing-block-id="{{ data_get($footerCta??[],'id') }}" data-block-type="ca0050_footer">
 @include('theme-ca0050::partials.edit-button',['block'=>$footerCta??[]])
 <div class="ca50-footer-wave"></div><div class="ca50-footer-grid">
  <section><a class="ca50-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif</a><p>Sudes Aquarium là cửa hàng chuyên cung cấp cá cảnh, phụ kiện và dịch vụ setup bể thủy sinh trọn gói.</p><p><b>📍 Địa chỉ:</b> {{ $address }}</p><p><b>📞 Điện thoại:</b> {{ $phone }}</p><p><b>✉ Email:</b> {{ $email }}</p></section>
  <section><h3>CHÍNH SÁCH</h3><a href="#">Chính sách bảo mật</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách đổi trả</a><a href="#">Quy định sử dụng</a></section>
  <section><h3>HƯỚNG DẪN</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Hướng dẫn giao nhận</a><a href="#">Điều khoản dịch vụ</a></section>
  <section><h3>{{ data_get($footerCta??[],'data.title','ĐĂNG KÝ NHẬN TIN KHUYẾN MÃI') }}</h3><p>{{ data_get($footerCta??[],'data.description','Đăng ký nhận tin khuyến mãi từ Sudes Aquarium để không bỏ lỡ các ưu đãi hấp dẫn!') }}</p><form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="ca0050-footer"><input type="email" name="email" required placeholder="Nhập địa chỉ email"><button>→</button></form><h3>THEO DÕI CHÚNG TÔI</h3><div class="ca50-social"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-instagram"></i><i class="fa-brands fa-tiktok"></i><i class="fa-brands fa-youtube"></i></div></section>
 </div>
</footer>
