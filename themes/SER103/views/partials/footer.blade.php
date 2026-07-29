@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'Bøhu.');
@endphp
<footer class="ser103-footer">
    <div class="ser103-container">
        <div class="ser103-footer__grid">
            <section class="ser103-footer__brand">
                <a class="ser103-brand" href="{{ route('site.home') }}">{{ $companyName }}</a>
                <p>{{ data_get($branding, 'company_description', 'Studio cưới đồng hành cùng các cặp đôi viết nên câu chuyện tình yêu của riêng mình bằng trải nghiệm tinh tế và đáng nhớ.') }}</p>
                <div class="ser103-socials"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a><a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a></div>
            </section>
            <section><h3>Danh mục dịch vụ</h3><a href="{{ route('site.services.index') }}">Trang điểm cô dâu</a><a href="{{ route('site.services.index') }}">Quay phim - chụp ảnh</a><a href="{{ route('site.services.index') }}">Thuê xe cưới</a><a href="{{ route('site.services.index') }}">Trang trí tiệc cưới</a></section>
            <section><h3>Video nổi bật</h3><div class="ser103-video-grid">@foreach(['/theme-previews/SER103/gallery-aodai.webp','/theme-previews/SER103/service-banquet.webp','/theme-previews/SER103/gallery-mountain.webp','/theme-previews/SER103/gallery-lake.webp'] as $image)<a href="#"><img src="{{ $image }}" alt="Video cưới"><i class="fa-solid fa-play"></i></a>@endforeach</div></section>
            <section><h3>Đăng ký nhận tin</h3><p>Đăng ký nhận bản tin hàng tuần để nhận thông tin cập nhật mới nhất.</p><form class="ser103-newsletter" action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Địa chỉ email..." required><button type="submit"><i class="fa-regular fa-paper-plane"></i> Đăng ký</button></form></section>
        </div>
        <div class="ser103-footer__bottom">© {{ now()->year }} {{ $companyName }}. All rights reserved.</div>
    </div>
</footer>
