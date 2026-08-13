@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) data_get($siteProfile ?? [], 'site_name', 'NEWS88'));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $company = trim((string) data_get($branding, 'company_name', $siteName));
    $description = trim((string) data_get($branding, 'company_description', data_get($siteProfile ?? [], 'description', '')));
    $address = trim((string) data_get($branding, 'support_location'));
    $hotline = trim((string) data_get($branding, 'support_hotline'));
    $email = trim((string) data_get($branding, 'support_email'));
    $copyright = trim((string) data_get($branding, 'copyright_text'));
    $footerBlock = collect($landingBlocks ?? [])->first(fn($block) => data_get($block, 'block_type') === 'news88_footer_posts');
    $footerPosts = collect(data_get($footerBlock, 'dynamic_items', data_get($footerBlock, 'data.content.items', [])))->take(4);
    $tags = collect(data_get($shell, 'top_menu', []))->pluck('label')->filter()->take(10);
@endphp
<footer class="n88-footer" id="footer">
    <div class="n88-container n88-footer-grid">
        <section class="n88-footer-about">
            <a class="n88-footer-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<strong>{{ $siteName }}</strong>@endif</a>
            <p><strong>{{ $company }}</strong>{{ filled($description) ? ' — '.$description : '' }}</p>
            @if($hotline)<p><i class="fa-solid fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}">{{ $hotline }}</a></p>@endif
            @if($email)<p><i class="fa-regular fa-envelope"></i> <a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            <div class="n88-footer-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></div>
        </section>
        <section><h2>@themeT('NEWS88.recent', 'Tin Gần Đây')</h2><div class="n88-footer-posts">@foreach($footerPosts as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a>@endforeach</div></section>
        <section><h2>@themeT('NEWS88.keywords', 'Từ Khóa')</h2><div class="n88-tags">@foreach($tags as $tag)<a href="{{ route('site.catalog.search', ['q' => $tag]) }}">{{ mb_strtolower($tag) }}</a>@endforeach</div></section>
        <section class="n88-map">@if($address)<iframe title="{{ $address }}" loading="lazy" src="https://www.google.com/maps?q={{ urlencode($address) }}&output=embed"></iframe>@else<div><i class="fa-solid fa-location-dot"></i><span>{{ $company }}</span></div>@endif</section>
    </div>
    <div class="n88-footer-bottom"><div class="n88-container">{{ $copyright !== '' ? $copyright : '© '.now()->year.' '.$company.'. All rights reserved.' }}</div></div>
</footer>
