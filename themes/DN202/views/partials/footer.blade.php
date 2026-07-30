@php
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('DN202', app()->getLocale(), $key);
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $hotline = $branding['support_hotline'] ?? '0399162342';
    $email = $branding['support_email'] ?? 'hello@dn202.vn';
    $address = $branding['support_location'] ?? 'An Thượng, Hà Nội';
    $footerLinks = [['label' => $t('nav.home'), 'url' => route('site.home')], ['label' => $t('nav.products'), 'url' => route('site.catalog.search')], ['label' => $t('nav.projects'), 'url' => route('site.projects.index')], ['label' => $t('nav.villas'), 'url' => route('site.home').'#thiet-ke-biet-thu'], ['label' => $t('nav.news'), 'url' => route('site.blog.index')], ['label' => $t('nav.contact'), 'url' => route('site.contact')]];
@endphp
<footer id="lien-he" class="d202-footer">
    <div class="d202-container d202-footer-grid">
        <section><h3>{{ $t('footer.help') }}</h3><h4>{{ $t('footer.free_consulting') }}</h4><a class="d202-hotline" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">{{ $hotline }}</a><p>{{ $address }}<br><a href="mailto:{{ $email }}">{{ $email }}</a></p><div class="d202-social"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a></div></section>
        <section><h3>{{ $t('footer.directions') }}</h3><a class="d202-map" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($address) }}" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i><span>{{ $address }}</span></a></section>
        <section><h3>{{ $t('footer.policy') }}</h3>@foreach($footerLinks as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</section>
        <section><h3>{{ $t('footer.guide') }}</h3>@foreach($footerLinks as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</section>
    </div>
    <div class="d202-container d202-copyright">© {{ now()->year }} {{ $t('footer.copyright') }}</div>
    <a class="d202-backtop" href="#top" aria-label="Back to top"><i class="fa-solid fa-chevron-up"></i></a>
</footer>
