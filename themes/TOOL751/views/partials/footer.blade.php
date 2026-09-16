@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'BeeTools Store'))) ?: 'BeeTools Store';
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $description = trim((string) data_get($branding, 'company_description', ''));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $copyright = trim((string) data_get($branding, 'copyright_text', '')) ?: '© '.date('Y').' '.$company;
@endphp
<section class="t751-newsletter"><div class="t751-container"><div><h2>@themeT('newsletter', 'Đăng ký nhận thông tin')</h2><p>@themeT('newsletter.description', 'Bạn có muốn là người đầu tiên nhận khuyến mãi hấp dẫn từ chúng tôi?')</p></div><form method="POST" action="{{ route('site.newsletter.subscribe', ['locale' => app()->getLocale()]) }}">@csrf<input type="email" name="email" required placeholder="@themeT('newsletter.placeholder', 'Email nhận tin')"><button>@themeT('newsletter.submit', 'Đăng ký ngay')</button></form></div></section>
<footer id="lien-he" class="t751-footer"><div class="t751-container t751-footer-grid">
    <section class="t751-footer-brand"><a class="t751-logo" href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span class="t751-logo-mark">B</span><span><b>{{ $company }}</b><small>{{ data_get($branding, 'slogan', __('Dụng cụ cơ khí cho mọi nhà')) }}</small></span>@endif</a><p>{{ $description ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'footer.about_copy', 'Kênh dụng cụ cơ khí đáng tin cậy cho gia đình, xưởng máy và công trình.') }}</p><div class="t751-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a></div></section>
    <section><h3>@themeT('footer.info', 'Thông tin')</h3><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@themeT('home', 'Trang chủ')</a><a href="#gioi-thieu">@themeT('about', 'Giới thiệu')</a><a href="#san-pham">@themeT('products', 'Sản phẩm')</a><a href="#tin-tuc">@themeT('news', 'Tin tức')</a><a href="#lien-he">@themeT('contact', 'Liên hệ')</a></section>
    <section><h3>@themeT('footer.policies', 'Chính sách')</h3><a href="#">{{ __('Hướng dẫn mua hàng') }}</a><a href="#">{{ __('Chính sách đổi trả') }}</a><a href="#">{{ __('Giao hàng và thanh toán') }}</a><a href="#">{{ __('Điều khoản sử dụng') }}</a></section>
    <section><h3>@themeT('footer.contact', 'Thông tin liên hệ')</h3>@if($address)<p><i class="fa-solid fa-location-dot"></i>{{ $address }}</p>@endif @if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif @if($email)<a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i>{{ $email }}</a>@endif</section>
</div><div class="t751-copyright">{{ $copyright }}</div><a class="t751-to-top" href="#top" aria-label="@themeT('footer.back_to_top', 'Lên đầu trang')"><i class="fa-solid fa-chevron-up"></i></a></footer>
