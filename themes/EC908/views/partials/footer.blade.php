@php($shell=$themeShellData??$themeHomeData??[])
@php($branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[])))
@php($logo=trim((string)data_get($branding,'logo_url','')))
@php($siteName=trim((string)data_get($branding,'company_name',data_get($siteProfile??[],'site_name','Ego Fitness')))?:'Ego Fitness')
<footer class="ec98-footer"><div class="ec98-container ec98-footer-brand"><a class="ec98-logo is-footer" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span class="ec98-logo-mark">e</span><span><b>ego</b> <strong>fitness</strong><small>STUDIOS</small></span>@endif</a></div><div class="ec98-container ec98-footer-grid">
    <section><h3>HỖ TRỢ / DỊCH VỤ</h3><a href="{{ route('site.contact') }}">Hỗ trợ khách hàng</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn đổi trả</a><a href="#">Hướng dẫn sử dụng</a></section>
    <section><h3>CHÍNH SÁCH CHUNG</h3><a href="#">Chính sách sản phẩm</a><a href="#">Chính sách mua hàng</a><a href="#">Chính sách sau bán</a><a href="#">Chính sách thành viên</a><a href="#">Chính sách chi nhánh</a></section>
    <section><h3>THÔNG TIN CHUNG</h3><a href="{{ route('site.catalog.search') }}">Bảng giá sản phẩm</a><a href="#">Thành phần sản phẩm</a><a href="#">Chứng nhận sản phẩm</a><a href="#">Cơ sở sản xuất</a><a href="#">Chất lượng sản phẩm</a></section>
    <section><h3>VỀ CHÚNG TÔI</h3><p>Nếu bạn cần hỗ trợ hoặc có thắc mắc gì, vui lòng liên hệ ngay với chúng tôi</p><p><i class="fa-solid fa-location-dot"></i> {{ data_get($branding,'support_location','530 Thụy Khuê - Tây Hồ, Hà Nội') }}</p><p><i class="fa-solid fa-phone"></i> {{ data_get($branding,'support_hotline','19006750') }}</p><p><i class="fa-solid fa-envelope"></i> {{ data_get($branding,'support_email','support@egofitness.vn') }}</p></section>
</div><div class="ec98-container ec98-copy">Bản quyền thuộc về <b>Ego Creative</b> · Cung cấp bởi <b>Sapo</b></div></footer>
<a class="ec98-backtop" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-angle-up"></i></a>
