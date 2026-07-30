@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $gallery = $productGallery ?? [];
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/ser0101-product/960/720');
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $serviceDossier = [
        ['label' => 'Kênh ưu tiên', 'value' => 'Booking concierge'],
        ['label' => 'Hotline', 'value' => $contactHotline],
        ['label' => 'Khu vực phục vụ', 'value' => $contactLocation],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $product['title'] }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            :root {
                --n: #0f172f;
                --night: #08111f;
                --p: #0f766e;
                --o: #b45309;
                --a: #f0b429;
                --l: #d6e2de;
                --m: #5d7288;
            }

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
                color: #243b53;
            }

            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .top, .footer { background: var(--night); color: #d9e2ec; }
            .top .wrap, .head-inner, .footer-inner, .hero-meta { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
            .top { padding: 10px 0; font-size: 13px; }
            .head { padding: 18px 0; background: rgba(255, 255, 255, 0.94); border-bottom: 1px solid rgba(217, 226, 236, 0.9); }
            .brand strong { display: block; color: var(--night); font-size: 18px; }
            .brand span { color: var(--m); font-size: 13px; }
            .menu { display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; font-weight: 700; }
            .hero-shell { padding: 24px 0 34px; }
            .hero-banner {
                margin-bottom: 20px;
                padding: 20px;
                border-radius: 34px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: 0 26px 58px rgba(10, 30, 47, 0.16);
            }
            .hero-banner-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) 300px; gap: 18px; align-items: stretch; }
            .hero-banner-copy { padding: 6px 10px 2px; }
            .eyebrow { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero-banner h1 { margin: 16px 0 12px; font-size: clamp(34px, 4.5vw, 58px); line-height: 1.02; }
            .hero-banner p { max-width: 820px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-meta { margin-top: 18px; }
            .hero-pill { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 13px; font-weight: 700; }
            .hero-dossier {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-dossier span { color: #fce7b2; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero-dossier h3 { margin: 0; font-size: 24px; line-height: 1.12; }
            .hero-dossier p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-dossier-list { display: grid; gap: 10px; }
            .hero-dossier-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-dossier-item strong { display: block; font-size: 18px; }
            .hero-dossier-item small { color: #cbd5e1; line-height: 1.5; }
            .hero { display: grid; grid-template-columns: minmax(0, 1.02fr) minmax(340px, 0.98fr); gap: 20px; align-items: start; }
            .panel { border: 1px solid rgba(217, 226, 236, 0.92); border-radius: 28px; background: rgba(255, 255, 255, 0.94); box-shadow: 0 22px 48px rgba(16, 42, 67, 0.08); }
            .gallery, .copy, .section { padding: 22px; }
            .gallery img.main {
                width: 100%;
                aspect-ratio: 1 / 1;
                object-fit: cover;
                border-radius: 22px;
                background: #edf2f7;
                transition: opacity 0.24s ease, transform 0.24s ease;
            }
            .gallery img.main.is-switching {
                opacity: 0.58;
                transform: scale(0.985);
            }
            .thumbs {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 88px));
                justify-content: start;
                gap: 10px;
                margin-top: 12px;
            }
            .thumbs button {
                display: block;
                width: 100%;
                padding: 0;
                border: 0;
                background: transparent;
                border-radius: 16px;
                cursor: pointer;
                transition: transform 0.18s ease, box-shadow 0.18s ease;
            }
            .thumbs button:hover { transform: translateY(-2px); }
            .thumbs button:focus-visible {
                outline: 3px solid rgba(15, 118, 110, 0.22);
                outline-offset: 3px;
            }
            .thumbs button.is-active {
                box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.18);
            }
            .thumbs img {
                width: 100%;
                aspect-ratio: 1 / 1;
                border-radius: 16px;
                object-fit: cover;
                border: 1px solid rgba(217, 226, 236, 0.92);
                transition: border-color 0.18s ease, opacity 0.18s ease;
            }
            .thumbs button.is-active img {
                border-color: rgba(15, 118, 110, 0.52);
                opacity: 1;
            }
            .thumbs button:not(.is-active) img { opacity: 0.82; }
            .service-tag { display: inline-flex; margin-bottom: 12px; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--p) 8%, white); color: var(--p); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
            .copy h2 { margin: 0 0 12px; color: var(--night); font-size: 38px; line-height: 1.08; }
            .copy p { margin: 0; color: var(--m); line-height: 1.8; }
            .mini-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
            .mini-card { padding: 14px; border-radius: 20px; background: linear-gradient(180deg, #ffffff, #f5faf8); border: 1px solid rgba(214, 226, 222, 0.92); }
            .mini-card strong { display: block; color: var(--night); font-size: 18px; }
            .mini-card span { display: block; margin-top: 6px; color: var(--m); line-height: 1.65; font-size: 13px; }
            .price { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin: 20px 0; }
            .price strong { font-size: 40px; color: var(--o); }
            .price del { color: #9fb3c8; }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
            .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 18px; border-radius: 999px; font-weight: 800; }
            .btn.primary { background: linear-gradient(135deg, var(--p), var(--p-deep)); color: #fff; box-shadow: 0 14px 28px color-mix(in srgb, var(--p) 20%, transparent); }
            .btn.secondary { background: #fff; border: 1px solid rgba(217, 226, 236, 0.92); color: var(--night); }
            .btn.secondary button { border: 0; background: transparent; font: inherit; color: inherit; padding: 0; }
            .details { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 20px; padding-bottom: 34px; align-items: start; }
            .section h3 { margin: 0 0 14px; color: var(--night); font-size: 26px; }
            .section p { margin: 0 0 12px; color: #334e68; line-height: 1.9; }
            .highlights { display: grid; gap: 12px; }
            .highlight-item { display: flex; gap: 12px; align-items: flex-start; padding: 14px 0; border-bottom: 1px dashed rgba(217, 226, 236, 0.9); }
            .highlight-item:last-child { border-bottom: 0; }
            .highlight-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 14px; background: color-mix(in srgb, var(--a) 14%, white); color: var(--o); font-weight: 800; }
            .highlight-item span { color: #334e68; line-height: 1.75; }
            .booking-card { position: sticky; top: 110px; display: grid; gap: 16px; align-self: start; }
            .booking-card .panel { padding: 22px; }
            .booking-card h4 { margin: 0 0 12px; color: var(--night); font-size: 22px; }
            .booking-card p { margin: 0; color: var(--m); line-height: 1.8; }
            .booking-dossier { background: linear-gradient(180deg, #fffaf1, #f5fbf8); }
            .booking-dossier-list { display: grid; gap: 10px; margin-top: 14px; }
            .booking-dossier-item { padding-top: 10px; border-top: 1px dashed color-mix(in srgb, var(--a) 18%, transparent); }
            .booking-dossier-item strong { display: block; color: var(--night); }
            .booking-dossier-item span { color: var(--m); line-height: 1.6; font-size: 14px; }
            .cart-feedback { margin-top: 12px; padding: 12px 14px; border-radius: 16px; font-size: 14px; font-weight: 700; line-height: 1.6; }
            .cart-feedback[data-state="success"] { background: color-mix(in srgb, var(--p) 10%, white); color: var(--p-deep); }
            .cart-feedback[data-state="error"] { background: rgba(185, 28, 28, 0.08); color: #991b1b; }
            .step-list { display: grid; gap: 12px; margin-top: 16px; }
            .step { display: flex; gap: 12px; align-items: flex-start; }
            .step-index { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 12px; background: var(--night); color: #fff; font-size: 13px; font-weight: 800; }
            .step strong { display: block; margin-bottom: 4px; color: var(--night); }
            .step span { color: var(--m); line-height: 1.7; font-size: 14px; }

            @media (max-width: 980px) {
                .hero, .details, .hero-banner-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .thumbs {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
                .mini-metrics { grid-template-columns: 1fr 1fr; }
            }
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap hero-shell">
            <section class="hero-banner">
                <div class="hero-banner-grid">
                    <div class="hero-banner-copy">
                        <span class="eyebrow">Chi tiết gói dịch vụ</span>
                        <h1>{{ $product['title'] }}</h1>
                        <p>{{ $productModel->short_description ?: 'Trang chi tiết này được tối ưu để hiển thị bảng giá tham khảo, điều kiện sử dụng và CTA báo giá nhanh cho nhà xe, shuttle và dịch vụ hợp đồng.' }}</p>
                        <div class="hero-meta">
                            <span class="hero-pill">Hotline {{ $contactHotline }}</span>
                            <span class="hero-pill">{{ $productModel->sku ?? 'AIO-SER' }}</span>
                            <span class="hero-pill">Checkout ưu tiên báo giá</span>
                        </div>
                    </div>
                    <aside class="hero-dossier">
                        <span>Service dossier</span>
                        <h3>Gói đặt xe được trình bày như một booking brief.</h3>
                        <p>Thay vì nhấn cảm giác catalog đơn thuần, SER0101 đẩy rõ lộ trình chốt nhu cầu, mức giá tham khảo và đầu mối điều phối.</p>
                        <div class="hero-dossier-list">
                            @foreach ($serviceDossier as $item)
                                <div class="hero-dossier-item">
                                    <strong>{{ $item['value'] }}</strong>
                                    <small>{{ $item['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>
            </section>

            <section class="hero">
                <div class="panel gallery" data-ser-product-gallery>
                    <img class="main" src="{{ $primaryImage }}" alt="{{ $product['title'] }}" data-ser-product-main-image>
                    @if ($gallery !== [])
                        <div class="thumbs">
                            @foreach ($gallery as $index => $image)
                                @php
                                    $thumbAlt = $image['alt'] ?? $product['title'];
                                @endphp
                                <button type="button" data-ser-product-thumb data-image-src="{{ $image['url'] }}" data-image-alt="{{ $thumbAlt }}" class="{{ $index === 0 ? 'is-active' : '' }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                    <img src="{{ $image['url'] }}" alt="{{ $thumbAlt }}">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="panel copy">
                    <span class="service-tag">{{ $product['tag'] ?? $t('product.service_tag_default', 'Gói dịch vụ') }}</span>
                    <h2>{{ $product['title'] }}</h2>
                    <p>{{ $productModel->short_description ?: $t('product.short_description_fallback', 'Thông tin được trình bày theo gu của nhà xe: giá tham khảo, điều kiện sử dụng, ghi chú lộ trình và CTA liên hệ điều phối.') }}</p>

                    <div class="mini-metrics">
                        <div class="mini-card">
                            <strong>{{ $t('product.metric_response_title', 'Phản hồi nhanh') }}</strong>
                            <span>{{ $t('product.metric_response_body', 'Hotline và form request được đặt ở các điểm chốt trên toàn bộ hành trình.') }}</span>
                        </div>
                        <div class="mini-card">
                            <strong>{{ $t('product.metric_price_title', 'Giá linh hoạt') }}</strong>
                            <span>{{ $t('product.metric_price_body', 'Mức hiển thị là tham khảo, có thể thay đổi theo tuyến, giờ và quy mô.') }}</span>
                        </div>
                        <div class="mini-card">
                            <strong>{{ $t('product.metric_data_title', 'Vận dụng data cũ') }}</strong>
                            <span>{{ $t('product.metric_data_body', 'Vẫn chạy trên CatalogProduct và shell storefront hiện có.') }}</span>
                        </div>
                    </div>

                    <div class="price">
                        <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                        @if (($product['old_price'] ?? null) !== null)
                            <del>{{ $formatCurrency($product['old_price']) }}</del>
                        @endif
                    </div>

                    <div class="actions">
                        <button type="button" class="btn primary" data-open-quote-modal>{{ $t('product.buy_now', 'Nhận báo giá') }}</button>
                        <form method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}" data-ser-add-to-cart-form data-success-message="{{ $t('product.add_to_cart_success', 'Đã thêm dịch vụ vào giỏ hàng.') }}" data-error-message="{{ $t('product.add_to_cart_error', 'Không thể thêm dịch vụ vào giỏ hàng lúc này.') }}">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn secondary" data-loading-label="{{ $t('product.add_to_cart_loading', 'Đang thêm...') }}">{{ $t('product.add_to_cart', 'Thêm vào giỏ hàng') }}</button>
                        </form>
                    </div>
                    <div class="cart-feedback" data-ser-cart-feedback hidden aria-live="polite"></div>
                </div>
            </section>

            <section class="details">
                <article class="panel section">
                    <h3>{{ $t('product.highlights_title', 'Điểm nổi bật') }}</h3>
                    <div class="highlights">
                        @foreach ($highlights as $index => $item)
                            <div class="highlight-item">
                                <span class="highlight-icon">{{ $index + 1 }}</span>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>

                    <h3 style="margin-top: 26px;">{{ $t('product.description_title', 'Mô tả dịch vụ') }}</h3>
                    @foreach ($detailParagraphsList as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </article>

                <aside class="booking-card">
                    <div class="panel booking-dossier">
                        <h4>{{ $t('common.booking_dossier_title', 'Hồ sơ đặt xe') }}</h4>
                        <div class="booking-dossier-list">
                            @foreach ($serviceDossier as $item)
                                <div class="booking-dossier-item">
                                    <strong>{{ $item['label'] }}</strong>
                                    <span>{{ $item['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="panel">
                        <h4>{{ $t('product.usage_terms_title', 'Thông tin sử dụng') }}</h4>
                        <p>{!! nl2br(e($productModel->usage_terms ?? $t('product.usage_terms_fallback', 'Giá trên là mức tham khảo. Vui lòng gọi hotline để xác nhận lịch trình và giá cuối cùng.'))) !!}</p>
                    </div>

                    <div class="panel">
                        <h4>{{ $t('product.quick_booking_title', 'Quy trình đặt nhanh') }}</h4>
                        <div class="step-list">
                            <div class="step">
                                <span class="step-index">01</span>
                                <div>
                                    <strong>{{ $t('product.step_request_title', 'Gửi nhu cầu') }}</strong>
                                    <span>{{ $t('product.step_request_body', 'Cho biết điểm đón, điểm đến, số khách và khung giờ cần xe.') }}</span>
                                </div>
                            </div>
                            <div class="step">
                                <span class="step-index">02</span>
                                <div>
                                    <strong>{{ $t('product.step_confirm_title', 'Xác nhận lộ trình') }}</strong>
                                    <span>{{ $t('product.step_confirm_body', 'Điều phối viên gọi lại, đề xuất loại xe và bảng giá phù hợp.') }}</span>
                                </div>
                            </div>
                            <div class="step">
                                <span class="step-index">03</span>
                                <div>
                                    <strong>{{ $t('product.step_create_title', 'Khởi tạo chuyến') }}</strong>
                                    <span>{{ $t('product.step_create_body', 'Nhu cầu được lưu vào danh sách yêu cầu và tiếp tục qua bước gửi thông tin.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <h4>{{ $t('product.contact_title', 'Địa điểm / liên hệ') }}</h4>
                        <p>{!! nl2br(e($productModel->usage_location ?? data_get($branding, 'support_location', $t('product.usage_location_fallback', 'Hồ Chí Minh City')))) !!}</p>
                    </div>
                </aside>
            </section>
        </main>

        <footer class="footer">
            <div class="wrap footer-inner">
                <span>{{ data_get($branding, 'company_name', 'SER0101') }}</span>
                @include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'ser-footer-boc-status'])
                <span>{{ $contactHotline }}</span>
            </div>
        </footer>

        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        <script>
            (() => {
                const gallery = document.querySelector('[data-ser-product-gallery]');
                const mainImage = document.querySelector('[data-ser-product-main-image]');
                const thumbs = [...document.querySelectorAll('[data-ser-product-thumb]')];

                if (!gallery || !mainImage || thumbs.length === 0) {
                    return;
                }

                const setActiveThumb = (activeThumb) => {
                    thumbs.forEach((thumb) => {
                        const isActive = thumb === activeThumb;
                        thumb.classList.toggle('is-active', isActive);
                        thumb.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                };

                const switchMainImage = (thumb) => {
                    const nextSrc = thumb.dataset.imageSrc;
                    const nextAlt = thumb.dataset.imageAlt || mainImage.alt;

                    if (!nextSrc) {
                        return;
                    }

                    mainImage.classList.add('is-switching');
                    window.setTimeout(() => {
                        mainImage.src = nextSrc;
                        mainImage.alt = nextAlt;
                        mainImage.classList.remove('is-switching');
                    }, 120);
                    setActiveThumb(thumb);
                };

                const focusThumbByOffset = (currentThumb, offset) => {
                    const currentIndex = thumbs.indexOf(currentThumb);

                    if (currentIndex < 0) {
                        return;
                    }

                    const nextIndex = (currentIndex + offset + thumbs.length) % thumbs.length;
                    thumbs[nextIndex]?.focus();
                    switchMainImage(thumbs[nextIndex]);
                };

                thumbs.forEach((thumb) => {
                    thumb.addEventListener('click', () => {
                        switchMainImage(thumb);
                    });

                    thumb.addEventListener('keydown', (event) => {
                        if (event.key === 'ArrowRight') {
                            event.preventDefault();
                            focusThumbByOffset(thumb, 1);
                            return;
                        }

                        if (event.key === 'ArrowLeft') {
                            event.preventDefault();
                            focusThumbByOffset(thumb, -1);
                        }
                    });
                });
            })();
        </script>
    </body>
</html>
