@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $gallery = $productGallery ?? [];
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/ser0100-product/960/720');
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $product['title'] }} | {{ data_get($branding, 'company_name', 'SER0100') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0100::partials.shell-styles')

            :root {
                --n: #102a43;
                --night: #081a2a;
                --p: #1f6f78;
                --o: #c2410c;
                --a: #f59e0b;
                --l: #d9e2ec;
                --m: #627d98;
            }

            @include('theme-ser0100::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.14), transparent 30%), #f7fbfd;
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
                padding: 24px 26px;
                border-radius: 30px;
                background: linear-gradient(135deg, rgba(16, 42, 67, 0.98), rgba(31, 111, 120, 0.9));
                color: #fff;
                box-shadow: 0 26px 58px rgba(16, 42, 67, 0.14);
            }
            .eyebrow { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero-banner h1 { margin: 16px 0 12px; font-size: clamp(34px, 4.5vw, 58px); line-height: 1.02; }
            .hero-banner p { max-width: 820px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-meta { margin-top: 18px; }
            .hero-pill { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 13px; font-weight: 700; }
            .hero { display: grid; grid-template-columns: minmax(0, 1.02fr) minmax(340px, 0.98fr); gap: 20px; }
            .panel { border: 1px solid rgba(217, 226, 236, 0.92); border-radius: 28px; background: rgba(255, 255, 255, 0.94); box-shadow: 0 22px 48px rgba(16, 42, 67, 0.08); }
            .gallery, .copy, .section { padding: 22px; }
            .gallery img.main { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 22px; background: #edf2f7; }
            .thumbs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
            .thumbs img { width: 100%; aspect-ratio: 1 / 1; border-radius: 16px; object-fit: cover; border: 1px solid rgba(217, 226, 236, 0.92); }
            .service-tag { display: inline-flex; margin-bottom: 12px; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--p) 8%, white); color: var(--p); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
            .copy h2 { margin: 0 0 12px; color: var(--night); font-size: 38px; line-height: 1.08; }
            .copy p { margin: 0; color: var(--m); line-height: 1.8; }
            .mini-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
            .mini-card { padding: 14px; border-radius: 20px; background: linear-gradient(180deg, #f8fbfd, #eef5f7); border: 1px solid rgba(217, 226, 236, 0.92); }
            .mini-card strong { display: block; color: var(--night); font-size: 18px; }
            .mini-card span { display: block; margin-top: 6px; color: var(--m); line-height: 1.65; font-size: 13px; }
            .price { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin: 20px 0; }
            .price strong { font-size: 40px; color: var(--o); }
            .price del { color: #9fb3c8; }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 8px; }
            .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 18px; border-radius: 999px; font-weight: 800; }
            .btn.primary { background: linear-gradient(135deg, var(--o), var(--o-deep)); color: #fff; box-shadow: 0 14px 28px color-mix(in srgb, var(--o) 20%, transparent); }
            .btn.secondary { background: #fff; border: 1px solid rgba(217, 226, 236, 0.92); color: var(--night); }
            .btn.secondary button { border: 0; background: transparent; font: inherit; color: inherit; padding: 0; }
            .details { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 20px; padding-bottom: 34px; }
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
            .step-list { display: grid; gap: 12px; margin-top: 16px; }
            .step { display: flex; gap: 12px; align-items: flex-start; }
            .step-index { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 12px; background: var(--night); color: #fff; font-size: 13px; font-weight: 800; }
            .step strong { display: block; margin-bottom: 4px; color: var(--night); }
            .step span { color: var(--m); line-height: 1.7; font-size: 14px; }

            @media (max-width: 980px) {
                .hero, .details { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .thumbs, .mini-metrics { grid-template-columns: 1fr 1fr; }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap hero-shell">
            <section class="hero-banner">
                <span class="eyebrow">Chi tiết gói dịch vụ</span>
                <h1>{{ $product['title'] }}</h1>
                <p>{{ $productModel->short_description ?: 'Trang chi tiết này được tối ưu để hiển thị bảng giá tham khảo, điều kiện sử dụng và CTA báo giá nhanh cho nhà xe, shuttle và dịch vụ hợp đồng.' }}</p>
                <div class="hero-meta">
                    <span class="hero-pill">Hotline {{ $contactHotline }}</span>
                    <span class="hero-pill">{{ $productModel->sku ?? 'AIO-SER' }}</span>
                    <span class="hero-pill">Checkout ưu tiên báo giá</span>
                </div>
            </section>

            <section class="hero">
                <div class="panel gallery">
                    <img class="main" src="{{ $primaryImage }}" alt="{{ $product['title'] }}">
                    @if ($gallery !== [])
                        <div class="thumbs">
                            @foreach ($gallery as $image)
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? $product['title'] }}">
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
                        <form method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                            @csrf
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn secondary">{{ $t('product.add_to_cart', 'Thêm vào giỏ hàng') }}</button>
                        </form>
                    </div>
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
                <span>{{ data_get($branding, 'company_name', 'SER0100') }}</span>
                @include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'ser-footer-boc-status'])
                <span>{{ $contactHotline }}</span>
            </div>
        </footer>

        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
