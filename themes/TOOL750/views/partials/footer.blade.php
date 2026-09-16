@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $company = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'TOOL750'))) ?: 'TOOL750';
    $description = trim((string) data_get($branding, 'company_description', data_get($siteProfile ?? [], 'description', '')));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $copyright = trim((string) data_get($branding, 'copyright_text', '')) ?: '© '.date('Y').' '.$company;
@endphp
<section class="t750-newsletter" aria-label="@themeT('newsletter', 'Đăng ký để nhận bản tin')">
    <div class="t750-container">
        <div><i class="fa-regular fa-envelope"></i><span><b>@themeT('newsletter', 'Đăng ký để nhận bản tin')</b><small>@themeT('newsletter.description', 'Nhận thông tin sản phẩm, mẹo kỹ thuật và ưu đãi mới.')</small></span></div>
        <form method="POST" action="{{ route('site.newsletter.subscribe', ['locale' => app()->getLocale()]) }}">
            @csrf
            <input type="email" name="email" required placeholder="@themeT('newsletter.placeholder', 'Nhập địa chỉ email')">
            <button>@themeT('newsletter.submit', 'Đăng ký')</button>
        </form>
    </div>
</section>
<footer id="lien-he" class="t750-footer">
    <div class="t750-container t750-footer-grid">
        <section class="t750-footer-brand">
            <a class="t750-logo" href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span class="t750-mark"><i class="fa-solid fa-gears"></i></span><b>{{ $company }}</b>@endif
            </a>
            <p>{{ $description ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'footer.about_copy', 'Thiết bị cơ khí chính hãng cho xưởng máy, công trình và người thợ hiện đại.') }}</p>
        </section>
        <section>
            <h3>@themeT('footer.guide', 'Hướng dẫn')</h3>
            <a href="#san-pham">@themeT('footer.shopping', 'Hướng dẫn mua hàng')</a>
            <a href="{{ route('site.cart.index', ['locale' => app()->getLocale()]) }}">@themeT('footer.payment', 'Hướng dẫn thanh toán')</a>
            <a href="#lien-he">@themeT('footer.shipping', 'Giao hàng')</a>
        </section>
        <section>
            <h3>@themeT('footer.policies', 'Chính sách')</h3>
            <a href="{{ route('site.pages.show', ['locale' => app()->getLocale(), 'slug' => 'gioi-thieu']) }}">@themeT('about', 'Giới thiệu')</a>
            <a href="#lien-he">@themeT('footer.returns', 'Chính sách đổi trả')</a>
            <a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('news', 'Tin tức')</a>
        </section>
        <section>
            <h3>@themeT('footer.contact', 'Liên hệ')</h3>
            @if($address)<p><i class="fa-solid fa-location-dot"></i>{{ $address }}</p>@endif
            @if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif
            @if($email)<a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i>{{ $email }}</a>@endif
        </section>
    </div>
    <div class="t750-copyright">{{ $copyright }}</div>
    <a class="t750-to-top" href="#top" aria-label="@themeT('footer.back_to_top', 'Lên đầu trang')"><i class="fa-solid fa-chevron-up"></i></a>
</footer>
