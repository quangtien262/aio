@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Euro Farm'))) ?: 'Euro Farm';
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $email = trim((string) ($branding['support_email'] ?? 'support@htvietnam.vn')) ?: 'support@htvietnam.vn';
    $address = trim((string) ($branding['support_location'] ?? '70 Lữ Gia, Phường 15, Quận 11, TP.HCM')) ?: '70 Lữ Gia, Phường 15, Quận 11, TP.HCM';
    $description = trim((string) ($branding['company_description'] ?? 'Euro Farm là doanh nghiệp nông nghiệp tiên phong chuyên sản xuất và cung cấp thực phẩm hữu cơ, an toàn và tốt cho sức khỏe.')) ?: 'Euro Farm là doanh nghiệp nông nghiệp tiên phong chuyên sản xuất và cung cấp thực phẩm hữu cơ, an toàn và tốt cho sức khỏe.';
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0323', app()->getLocale(), $key);
    $newsletterUrl = route('site.newsletter.subscribe', \App\Support\FrontendLocalization::routeParameterDefaults());
@endphp
<footer id="footer" class="xd323-footer">
    <div class="xd323-footer__shade">
        <div class="xd323-container xd323-footer__grid">
            <section class="xd323-newsletter">
                <i class="fa-regular fa-envelope-open"></i>
                <h2>{{ $themeText('XD0323.footer.newsletter_title') }}</h2>
                <p>{{ $themeText('XD0323.footer.newsletter_text') }}</p>
                <form method="POST" action="{{ $newsletterUrl }}">
                    @csrf
                    <input type="email" name="email" placeholder="{{ $themeText('XD0323.footer.newsletter_placeholder') }}" required>
                    <button type="submit">{{ $themeText('XD0323.footer.newsletter_button') }}</button>
                </form>
            </section>
            <section class="xd323-footer__main">
                <div class="xd323-footer__contact">
                    <p><i class="fa-solid fa-location-dot"></i><span><b>Địa chỉ:</b>{{ $address }}</span></p>
                    <p><i class="fa-solid fa-phone-volume"></i><span><b>Điện thoại:</b><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></span></p>
                    <p><i class="fa-solid fa-envelope"></i><span><b>Email:</b><a href="mailto:{{ $email }}">{{ $email }}</a></span></p>
                </div>
                <div class="xd323-footer__columns">
                    <nav>
                        <h3>{{ $themeText('XD0323.footer.policy') }}</h3>
                        <a href="#footer">{{ $themeText('XD0323.footer.member_policy') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.payment_policy') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.shipping_policy') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.privacy_policy') }}</a>
                    </nav>
                    <nav>
                        <h3>{{ $themeText('XD0323.footer.guide') }}</h3>
                        <a href="#footer">{{ $themeText('XD0323.footer.buying_guide') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.payment_guide') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.delivery_guide') }}</a>
                        <a href="#footer">{{ $themeText('XD0323.footer.terms') }}</a>
                    </nav>
                    <div>
                        <h3>{{ $themeText('XD0323.footer.info') }}</h3>
                        <p>{{ $description }}</p>
                        <div class="xd323-footer__social">
                            <a href="#footer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                            <a href="#footer" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                            <a href="#footer" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                            <a href="#footer" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a>
                            <a href="#footer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
                <div class="xd323-footer__bottom">{{ $themeText('XD0323.footer.rights') }}</div>
            </section>
        </div>
    </div>
</footer>
