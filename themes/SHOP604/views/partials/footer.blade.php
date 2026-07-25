@php
$shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$t=fn(string $key):string=>app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SHOP604',app()->getLocale(),$key);
$phone=$branding['support_hotline']??'1800 6750';$email=$branding['support_email']??'support@bean.vn';$address=$branding['support_location']??'70 Lữ Gia, Phường 15, Quận 11, TP. Hồ Chí Minh';
$policy=['Chính sách thành viên','Chính sách thanh toán','Chính sách đổi sản phẩm','Chính sách bảo mật','Chính sách bảo hành'];
$guide=['Hướng dẫn mua hàng','Hướng dẫn đổi trả','Hướng dẫn thanh toán','Điều khoản và điều kiện','Tìm kiếm sản phẩm'];
@endphp
<section
    class="s604-subscribe-bar xd-landing-block"
    data-landing-block-id="{{ data_get($newsletter ?? [], 'id') }}"
    data-block-type="shop604_newsletter"
>
    @include('theme-shop604::partials.edit-button', ['block' => $newsletter ?? []])
    <div><i class="fa-solid fa-phone-volume"></i><span>Gọi ngay chúng tôi<strong>{{ $phone }} (24/7)</strong></span></div>
    <div>
        <strong>{{ data_get($newsletter ?? [], 'data.title', 'Đăng Ký Nhận Tin') }}</strong>
        <span>{{ data_get($newsletter ?? [], 'data.description', 'Nhận thông tin về các chương trình khuyến mãi.') }}</span>
    </div>
    <form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="shop604-footer"><input type="email" name="email" required placeholder="{{ $t('SHOP604.newsletter.placeholder') }}"><button>{{ $t('SHOP604.newsletter.button') }}</button></form>
</section>
<footer class="s604-footer"><div class="s604-footer-grid">
    <section class="s604-footer-brand"><a class="s604-logo s604-logo-light" href="{{ route('site.home') }}"><span>B</span><strong>ean</strong><small>LINGERIE</small></a><p>{{ $t('SHOP604.footer.tagline') }}</p><p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p><p><i class="fa-solid fa-phone"></i> <b>{{ $phone }}</b></p><p><i class="fa-regular fa-envelope"></i> <b>{{ $email }}</b></p></section>
    <section><h3>{{ $t('SHOP604.footer.policy') }}</h3>@foreach($policy as $label)<a href="{{ route('site.contact') }}">{{ $label }}</a>@endforeach</section>
    <section><h3>{{ $t('SHOP604.footer.guide') }}</h3>@foreach($guide as $label)<a href="{{ route('site.contact') }}">{{ $label }}</a>@endforeach</section>
    <section><h3>{{ $t('SHOP604.footer.connect') }}</h3><div class="s604-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div><h3>Chấp Nhận Thanh Toán</h3><div class="s604-payments"><span>MOMO</span><span>ZaloPay</span><span>VNPAY</span></div></section>
</div><div class="s604-copyright">© {{ $t('SHOP604.footer.copyright') }}</div></footer>
