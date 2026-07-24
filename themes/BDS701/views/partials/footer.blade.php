<footer class="bds-footer">
    <div class="bds-container bds-footer-grid">
        <div>
            <h3>Về chúng tôi</h3>
            <p>Delta Platinum kết nối khách hàng với những bất động sản phù hợp để an cư, cho thuê và đầu tư dài hạn.</p>
        </div>
        <div><h3>Chính sách</h3><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">Trang chủ</a><a href="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">Tất cả tin rao</a><a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">Tin tức</a></div>
        <div><h3>Liên hệ</h3><strong>1900 6750</strong><p>{{ data_get($siteProfile ?? [], 'address', 'An Thượng, Hà Nội') }}</p><p>{{ data_get($siteProfile ?? [], 'email', 'contact@example.com') }}</p></div>
        <div><h3>Hướng dẫn</h3><a href="{{ route('site.pages.show', ['locale' => app()->getLocale(), 'slug' => 'gioi-thieu']) }}">Giới thiệu</a><a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">Liên hệ</a></div>
    </div>
    <a class="bds-to-top" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-chevron-up"></i></a>
</footer>
