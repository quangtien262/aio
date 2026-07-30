@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'WolfArch'))) ?: 'WolfArch';
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $email = trim((string) ($branding['support_email'] ?? 'support@htvietnam.vn')) ?: 'support@htvietnam.vn';
    $address = trim((string) ($branding['support_location'] ?? '70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh')) ?: '70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh';
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0324', app()->getLocale(), $key);
@endphp
<footer id="footer" class="xd324-footer">
    <div class="xd324-container">
        <div class="xd324-footer__top">
            <a class="xd324-footer__logo" href="{{ route('site.home') }}">{{ $companyName }}</a>
            <div class="xd324-footer__social">
                <strong>{{ $themeText('XD0324.footer.connect') }}</strong>
                <a href="#footer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#footer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#footer" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
            </div>
        </div>
        <div class="xd324-footer__grid">
            <section>
                <h3>{{ $companyName }}</h3>
                <p><i class="fa-regular fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p>
                <p><i class="fa-solid fa-location-dot"></i>{{ $address }}</p>
                <p><i class="fa-solid fa-phone"></i><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></p>
            </section>
            <section>
                <h3>{{ $themeText('XD0324.footer.about') }}</h3>
                <a href="#gioi-thieu">Giới thiệu Wolf Arch</a>
                <a href="#lien-he">Tuyển dụng</a>
                <a href="#du-an">Dự án đã thực hiện</a>
            </section>
            <section>
                <h3>{{ $themeText('XD0324.footer.portfolio') }}</h3>
                <a href="#dich-vu">Thiết kế nội thất nhà ở</a>
                <a href="#du-an">Thiết kế nhà sang trọng</a>
                <a href="#dich-vu">Thiết kế không gian thương mại</a>
                <a href="#dich-vu">Thiết kế cải tạo và phục hồi</a>
            </section>
            <section>
                <h3>{{ $themeText('XD0324.footer.styles') }}</h3>
                <a href="#xu-huong">Minimalist tối giản</a>
                <a href="#xu-huong">Modern Hiện đại</a>
                <a href="#xu-huong">Cổ điển - Tân Cổ Điển</a>
                <a href="#xu-huong">Thô Mộc</a>
            </section>
        </div>
        <div class="xd324-footer__bottom">&copy; {{ $themeText('XD0324.footer.rights') }}</div>
    </div>
</footer>
