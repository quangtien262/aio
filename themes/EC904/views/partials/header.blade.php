@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $brand = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($brand, 'logo_url', ''));
    $hotline = data_get($brand, 'support_hotline', '');
    $menuItems = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec94-header">
    <div class="ec94-head-main"><div class="ec94-container">
        <a class="ec94-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name') }}">@endif</a>
        <form action="{{ route('site.catalog.search') }}" method="get"><input name="q" placeholder="@themeT('search.placeholder', 'Tìm kiếm sản phẩm...')"><button aria-label="@themeT('search.label', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <a class="ec94-support" href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone-volume"></i><span>Tư vấn hỗ trợ<b>{{ $hotline }}</b></span></a>
        <button class="ec94-login" type="button" data-auth-open="login"><i class="fa-regular fa-circle-user"></i><span>Xin chào!<b>Đăng nhập</b></span></button>
        <div class="ec94-actions"><a href="#"><i class="fa-regular fa-heart"></i><b>0</b></a><a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><b>0</b></a><a href="#"><i class="fa-solid fa-shuffle"></i><b>0</b></a></div>
    </div></div>
    <nav class="ec94-nav"><div class="ec94-container">
        <button class="ec94-category-button" type="button" data-ec94-mega><i class="fa-solid fa-bars"></i> Danh mục sản phẩm</button>
        @foreach($menuItems as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        <section class="ec94-mega"><aside>
            @foreach(['Điện thoại - Máy tính bảng','Phụ kiện - Thiết bị số','Máy ảnh - Quay phim','Điện gia dụng - Nhà bếp','Laptop - Thiết bị IT','Máy chơi game - Trò chơi','Trang sức - Sành điệu','Thời trang - Làm đẹp','Nhà cửa đời sống'] as $index => $label)
                <a href="#danh-muc"><i class="{{ ['fa-solid fa-mobile-screen','fa-solid fa-headphones','fa-solid fa-camera','fa-solid fa-blender','fa-solid fa-laptop','fa-solid fa-gamepad','fa-regular fa-gem','fa-solid fa-shirt','fa-solid fa-couch'][$index] }}"></i>{{ $label }}<i class="fa-solid fa-angle-right"></i></a>
            @endforeach
        </aside><div><h3>Giảm giá cực hot 🔥</h3><p>Hàng loạt sản phẩm công nghệ, thời trang và gia dụng đang có giá tốt.</p><div><section><b>ĐIỆN THOẠI</b><a href="#dien-thoai">NovaPhone X</a><a href="#dien-thoai">NovaPhone Mini</a><a href="#dien-thoai">Điện thoại phổ thông</a></section><section><b>THIẾT BỊ SỐ</b><a href="#do-cong-nghe">Tai nghe</a><a href="#do-cong-nghe">Máy ảnh</a><a href="#do-cong-nghe">Laptop</a></section><section><b>ĐỜI SỐNG</b><a href="#thoi-trang">Thời trang</a><a href="#goi-y">Nhà cửa</a><a href="#goi-y">Phụ kiện</a></section></div></div></section>
    </div></nav>
</header>
@include('partials.storefront-language-switcher')
