<footer class="bds-footer">
    <div class="bds-container bds-footer-grid">
        <div>
            <h3>@themeT('footer.about_title', 'Về chúng tôi')</h3>
            <p>@themeT('footer.about_text', 'Delta Platinum kết nối khách hàng với những bất động sản phù hợp để an cư, cho thuê và đầu tư dài hạn.')</p>
        </div>
        <div><h3>@themeT('footer.policies', 'Chính sách')</h3><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@themeT('home', 'Trang chủ')</a><a href="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">@themeT('listings', 'Tất cả tin rao')</a><a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('news', 'Tin tức')</a></div>
        <div><h3>@themeT('footer.contact', 'Liên hệ')</h3><strong>1900 6750</strong><p>{{ data_get($siteProfile ?? [], 'address', 'An Thượng, Hà Nội') }}</p><p>{{ data_get($siteProfile ?? [], 'email', 'contact@example.com') }}</p></div>
        <div><h3>@themeT('footer.guides', 'Hướng dẫn')</h3><a href="{{ route('site.pages.show', ['locale' => app()->getLocale(), 'slug' => 'gioi-thieu']) }}">@themeT('about', 'Giới thiệu')</a><a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('contact', 'Liên hệ')</a></div>
    </div>
    <a class="bds-to-top" href="#top" aria-label="@themeT('footer.back_to_top', 'Lên đầu trang')"><i class="fa-solid fa-chevron-up"></i></a>
</footer>
