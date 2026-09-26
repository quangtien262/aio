<footer class="footer ser-shared-footer">
            <div class="wrap footer-inner">
                <div class="footer-grid">
                    <section class="footer-card">@include('themes.common.footer-logo')
                        <div class="footer-card-head">
                            <h4>{{ data_get($branding, 'company_name', 'SER0101') }}</h4>
                            @if ($canQuickEditThemeBlocks ?? false)
                                <button
                                    type="button"
                                    class="sf-inline-edit-btn"
                                    data-sf-inline-edit-trigger
                                    data-edit-title="Sửa footer SER0101"
                                    data-edit-fields='@json($footerEditFields)'
                                >
                                    Sửa footer
                                </button>
                            @endif
                        </div>
                        <p data-translation-display="home.footer_summary">{{ $t('home.footer_summary', 'Theme service-first cho nhà xe, shuttle doanh nghiệp và vận chuyển hàng nhẹ. Ưu tiên hotline, báo giá nhanh và nội dung tạo tin cậy.') }}</p>
                        @include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'ser-footer-boc-status'])
                    </section>
                    <section class="footer-card">
                        <h4 data-translation-display="home.footer_contact_title">{{ $t('home.footer_contact_title', 'Liên hệ') }}</h4>
                        <strong>{{ $contactHotline }}</strong>
                        <p>{{ $contactEmail }}</p>
                        <p>{{ $contactLocation }}</p>
                    </section>
                    <section class="footer-card">
                        <h4 data-translation-display="home.footer_nav_title">{{ $t('home.footer_nav_title', 'Điều hướng nhanh') }}</h4>
                        <a href="{{ route('site.home') }}" data-translation-display="common.home">{{ $t('common.home', 'Trang chủ') }}</a><br>
                        <a href="{{ route('site.blog.index') }}" data-translation-display="menu.default.blog">{{ $t('menu.default.blog', 'Cẩm nang') }}</a><br>
                        <a href="{{ route('site.catalog.search') }}" data-translation-display="common.search_button">{{ $t('common.search_button', 'Tìm') }}</a>
                    </section>
                </div>
            </div>
        </footer>
<style>.ser-shared-footer{background:#102a43;color:#d9e2ec;padding:32px 0}.ser-shared-footer .footer-inner{display:block;width:min(1180px,calc(100% - 40px));margin:auto}.ser-shared-footer .footer-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:28px;width:100%}.ser-shared-footer h4{color:white;margin:0 0 14px}.ser-shared-footer p,.ser-shared-footer a{color:#d9e2ec;line-height:1.8}.ser-shared-footer .footer-card-head{display:flex;gap:12px;align-items:center}@media(max-width:760px){.ser-shared-footer .footer-grid{grid-template-columns:1fr}}</style>
