@extends('theme-spa111::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Bean Spa')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $highlights = $get('spa111_service_highlights');
    $about = $get('spa111_about');
    $services = $get('spa111_services');
    $stats = $get('spa111_stats');
    $products = $get('spa111_featured_products');
    $why = $get('spa111_why_choose');
    $testimonials = $get('spa111_testimonials');
    $faq = $get('spa111_faq');
    $team = $get('spa111_team');
    $posts = $get('spa111_latest_posts');
    $partners = $get('spa111_partners');
    $booking = $get('spa111_booking');
    $footerBlock = $get('spa111_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    if ($slides->isEmpty()) $slides = collect([['title' => 'Nâng Niu Vẻ Đẹp Của Bạn', 'subtitle' => 'Chăm Sóc Sắc Đẹp Toàn Diện', 'image' => '/theme-demo/spa111/hero.png', 'button_label' => 'Xem thêm', 'link_url' => '#dich-vu']]);
    $serviceFallback = collect([
        ['title'=>'Massage Thư Giãn Toàn Thân','summary'=>'Liệu trình massage chuyên sâu giúp giải tỏa căng thẳng, giảm đau mỏi cơ và cải thiện tuần hoàn máu.','image'=>'/theme-demo/spa111/hero.png','icon'=>'fa-solid fa-spa','url'=>'#lien-he'],
        ['title'=>'Chăm Sóc Da Mặt Chuyên Sâu','summary'=>'Làm sạch, phục hồi và nuôi dưỡng làn da bằng sản phẩm cao cấp phù hợp từng loại da.','image'=>'/theme-demo/spa111/facial.png','icon'=>'fa-solid fa-face-smile-beam','url'=>'#lien-he'],
        ['title'=>'Massage Đá Nóng Trị Liệu','summary'=>'Sử dụng đá nóng tự nhiên giúp làm giãn cơ sâu, giảm đau nhức và phục hồi năng lượng.','image'=>'/theme-demo/spa111/hot-stone.png','icon'=>'fa-solid fa-fire-flame-simple','url'=>'#lien-he'],
    ]);
    $serviceItems = $items($services)->whenEmpty(fn () => $serviceFallback);
    $productItems = $items($products);
    $teamItems = $items($team);
    $postItems = $items($posts);
    $testimonialItems = $items($testimonials);
    $partnerItems = $items($partners);
@endphp
<main>
    <section class="sp11-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        @foreach($slides as $index => $slide)
            <article class="{{ $index === 0 ? 'is-active' : '' }}" data-spa111-hero-slide style="--hero:url('{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/spa111/hero.png')) }}')">
                <div class="sp11-hero-copy">
                    <span>{{ data_get($slide, 'subtitle', data_get($slide, 'kicker', 'Chăm Sóc Sắc Đẹp Toàn Diện')) }}</span>
                    <h1>{{ data_get($slide, 'title', 'Nâng Niu Vẻ Đẹp Của Bạn') }}</h1>
                    <a href="{{ data_get($slide, 'link_url', '#dich-vu') }}">{{ data_get($slide, 'button_label', 'Xem thêm') }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                </div>
            </article>
        @endforeach
        <button class="sp11-slider-arrow is-prev" data-spa111-hero-prev aria-label="Slide trước"><i class="fa-solid fa-arrow-left"></i></button>
        <button class="sp11-slider-arrow is-next" data-spa111-hero-next aria-label="Slide sau"><i class="fa-solid fa-arrow-right"></i></button>
        <div class="sp11-hero-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-spa111-hero-dot aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
    </section>

    <section class="sp11-highlights xd-landing-block" data-landing-block-id="{{ data_get($highlights, 'id') }}" data-block-type="spa111_service_highlights">
        <div class="sp11-flower">✿</div>
        <div class="sp11-container sp11-highlight-grid">
            @foreach([
                ['fa-solid fa-hands','Bấm Huyệt Bàn Chân','Liệu pháp giúp thư giãn sâu, kích thích tuần hoàn và mang lại cảm giác nhẹ nhàng'],
                ['fa-solid fa-head-side-virus','Massage Đầu Thư Giãn','Tăng cường tuần hoàn và mang lại cảm giác thư thái sâu cho vùng đầu và cổ.'],
                ['fa-solid fa-cubes-stacked','Massage Đá Nóng Thư Giãn','Sử dụng đá nóng đặt lên các điểm năng lượng giúp giải tỏa căng cơ, tăng tuần hoàn'],
            ] as [$icon,$title,$copy])
                <article><i class="{{ $icon }}"></i><div><h3>{{ $title }}</h3><p>{{ $copy }}</p></div></article>
            @endforeach
        </div>
    </section>

    <section id="gioi-thieu" class="sp11-about sp11-section xd-landing-block" data-landing-block-id="{{ data_get($about, 'id') }}" data-block-type="spa111_about">
        <div class="sp11-container sp11-about-card">
            <div class="sp11-about-images"><img src="/theme-demo/spa111/hot-stone.png" alt="Massage đá nóng"><img src="/theme-demo/spa111/facial.png" alt="Chăm sóc da mặt"><a href="tel:18006750"><i class="fa-solid fa-phone-volume"></i><span>Gọi điện ngay!<b>1800 6750</b></span></a></div>
            <div class="sp11-about-copy"><span class="sp11-eyebrow">VỀ CHÚNG TÔI</span><h2>{{ data_get($about, 'data.title', 'Chào mừng bạn đến với') }} <em>Bean Spa</em></h2><p>{{ data_get($about, 'data.description', 'Bean Spa mang đến trải nghiệm chăm sóc sức khỏe và sắc đẹp trọn vẹn, kết hợp liệu pháp thư giãn, dưỡng chất tự nhiên và không gian yên bình.') }}</p><div class="sp11-about-features"><span><i class="fa-solid fa-person-rays"></i>Thư giãn toàn thân</span><span><i class="fa-solid fa-leaf"></i>Dược liệu tự nhiên</span><span><i class="fa-solid fa-shoe-prints"></i>Trị liệu bàn chân</span><span><i class="fa-solid fa-hands-bubbles"></i>Phục hồi cơ thể</span></div><a class="sp11-btn" href="#dich-vu">Xem chi tiết <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
        </div>
    </section>

    <section id="dich-vu" class="sp11-services sp11-section xd-landing-block" data-landing-block-id="{{ data_get($services, 'id') }}" data-block-type="spa111_services">
        <div class="sp11-container"><div class="sp11-heading"><span class="sp11-eyebrow">DỊCH VỤ CỦA CHÚNG TÔI</span><h2>{{ data_get($services, 'data.title', 'Hành Trình Nâng Niu Bản Thân') }}</h2><p>{{ data_get($services, 'data.description', 'Chúng tôi kết hợp liệu trình tinh chỉnh, sản phẩm cao cấp và không gian yên tĩnh để tạo nên trải nghiệm thư giãn trọn vẹn.') }}</p></div><div class="sp11-service-grid">@foreach($serviceItems->take(3) as $item)<article><img src="{{ data_get($item, 'image', '/theme-demo/spa111/facial.png') }}" alt="{{ data_get($item, 'title') }}"><i class="{{ data_get($item, 'icon', 'fa-solid fa-spa') }}"></i><h3>{{ data_get($item, 'title') }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags(data_get($item, 'summary', '')), 140) }}</p><a href="{{ data_get($item, 'url', '#lien-he') }}">Xem chi tiết <i class="fa-solid fa-arrow-up-right-from-square"></i></a></article>@endforeach</div><div class="sp11-dots"><i></i><i></i><i></i><i></i></div></div>
    </section>

    <section class="sp11-stats xd-landing-block" data-landing-block-id="{{ data_get($stats, 'id') }}" data-block-type="spa111_stats"><div class="sp11-container">@foreach([['fa-solid fa-user-doctor','5000+','Khách Hàng Hài Lòng'],['fa-solid fa-ranking-star','98%','Đánh Giá Tích Cực'],['fa-solid fa-spa','10+','Liệu Trình Chuyên Sâu'],['fa-solid fa-medal','5 năm','Kinh Nghiệm Hoạt Động']] as [$icon,$value,$label])<article><i class="{{ $icon }}"></i><div><b>{{ $value }}</b><span>{{ $label }}</span></div></article>@endforeach</div></section>

    <section id="san-pham" class="sp11-products sp11-section xd-landing-block" data-landing-block-id="{{ data_get($products, 'id') }}" data-block-type="spa111_featured_products">
        <div class="sp11-container"><div class="sp11-heading"><h2>❧ {{ data_get($products, 'data.title', 'Sản Phẩm Nổi Bật') }} ❧</h2><p>{{ data_get($products, 'data.description', 'Các sản phẩm chăm sóc spa được tuyển chọn kỹ lưỡng, đảm bảo nguồn gốc rõ ràng và an toàn cho làn da.') }}</p><div class="sp11-tabs"><button class="is-active">Chăm sóc tóc</button><button>Chăm sóc da</button><button>Chăm sóc cơ thể</button></div></div><div class="sp11-product-grid">@foreach($productItems->take(8) as $index => $item)@php $price=(float)data_get($item,'price',99000);$old=(float)data_get($item,'old_price',data_get($item,'original_price',$price*1.2));$sale=$old>$price?round((1-$price/$old)*100):0; @endphp<article class="sp11-product"><div><span>- {{ $sale }}%</span><button aria-label="Yêu thích"><i class="fa-regular fa-heart"></i></button><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', '/theme-demo/spa111/product-shampoo.png') }}" alt="{{ data_get($item, 'title') }}"></a></div><h3>{{ \Illuminate\Support\Str::limit(data_get($item, 'title', 'Sản phẩm chăm sóc cao cấp'), 66) }}</h3><b>{{ number_format($price, 0, ',', '.') }}đ</b><del>{{ number_format($old, 0, ',', '.') }}đ</del></article>@endforeach</div></div>
    </section>

    <section class="sp11-why sp11-section xd-landing-block" data-landing-block-id="{{ data_get($why, 'id') }}" data-block-type="spa111_why_choose"><div class="sp11-container sp11-why-grid"><div><span class="sp11-eyebrow">TẠI SAO CHỌN CHÚNG TÔI</span><h2>Đồng hành cùng khách hàng chăm sóc <em>Sức Khỏe &amp; Thư Giãn</em></h2><p>Niềm tin được xây dựng từ sự tận tâm tuyệt đối dành cho sức khỏe và sự an yên của bạn. Chúng tôi kết hợp chuyên môn vững vàng với liệu trình được cá nhân hóa.</p><div class="sp11-why-points"><article><i class="fa-solid fa-briefcase-medical"></i><b>Chăm sóc sức khỏe toàn diện</b><span>Quan tâm đến bạn trọn vẹn, từ cơ thể, tinh thần đến cảm xúc.</span></article><article><i class="fa-solid fa-people-group"></i><b>Đội ngũ giàu kinh nghiệm &amp; tận tâm</b><span>Chuyên viên được chứng nhận, tay nghề cao và chân thành.</span></article></div></div><div class="sp11-why-photo"><img src="/theme-demo/spa111/why-choose.png" alt="Đồng hành cùng khách hàng"><b>5000+<small>Khách hàng</small></b></div></div></section>

    <section class="sp11-testimonials sp11-section xd-landing-block" data-landing-block-id="{{ data_get($testimonials, 'id') }}" data-block-type="spa111_testimonials"><div class="sp11-container sp11-testimonial-card"><div class="sp11-testimonial-top"><div><span class="sp11-eyebrow">PHẢN HỒI TỪ KHÁCH HÀNG</span><h2>Khách Hàng Nói Gì?</h2><p>Những lời chia sẻ chân thành là động lực để Bean Spa không ngừng hoàn thiện và nâng cao chất lượng dịch vụ mỗi ngày.</p></div><div class="sp11-google"><b>G</b><span>Đánh giá google<strong>5.0 ⭐⭐⭐⭐⭐</strong></span></div></div><div class="sp11-review-grid">@forelse($testimonialItems->take(3) as $item)<article><strong>★★★★★</strong><p>“{{ data_get($item,'quote',data_get($item,'summary','Dịch vụ tận tâm, không gian thư giãn và liệu trình rất hiệu quả.')) }}”</p><div><img src="{{ data_get($item,'image','/theme-demo/spa111/staff-minh-anh.png') }}" alt="{{ data_get($item,'title') }}"><span><b>{{ data_get($item,'title',data_get($item,'name','Nguyễn Thảo – Hà Nội')) }}</b><small>{{ data_get($item,'role','Khách hàng thân thiết') }}</small></span></div></article>@empty @foreach([['Nguyễn Thảo – Hà Nội','Nhân viên văn phòng','Mình rất hài lòng với dịch vụ chăm sóc da tại Bean Spa.'],['Minh Tuấn – TP.HCM','Kỹ sư','Kỹ thuật massage tốt, không gian sạch sẽ, thư giãn đúng nghĩa.'],['Thu Hà – Đà Nẵng','Kinh doanh','Điều mình thích nhất là sự tận tâm và mức giá hợp lý.']] as [$name,$role,$quote])<article><strong>★★★★★</strong><p>“{{ $quote }}”</p><div><img src="/theme-demo/spa111/staff-minh-anh.png" alt="{{ $name }}"><span><b>{{ $name }}</b><small>{{ $role }}</small></span></div></article>@endforeach @endforelse</div><div class="sp11-dots is-light"><i></i><i></i></div></div></section>

    <section id="faq" class="sp11-faq sp11-section xd-landing-block" data-landing-block-id="{{ data_get($faq, 'id') }}" data-block-type="spa111_faq"><div class="sp11-container sp11-faq-grid"><div><span class="sp11-eyebrow">CÂU HỎI THƯỜNG GẶP</span><h2>Giải Đáp Thắc Mắc Cùng <em>Bean Spa</em></h2><p>Nếu bạn không tìm thấy câu trả lời phù hợp, đừng ngần ngại liên hệ với chúng tôi để được hỗ trợ tốt nhất!</p><div class="sp11-accordion"><details open><summary>Bean Spa có dịch vụ dành cho nam giới không?</summary><p>Có. Bean Spa cung cấp các liệu trình spa dành cho nam giới được thiết kế chuyên biệt, giúp thư giãn cơ thể, chăm sóc làn da và phục hồi năng lượng.</p></details><details><summary>Giá liệu trình massage cặp đôi tại Bean Spa là bao nhiêu?</summary><p>Chi phí tùy thuộc liệu trình và thời lượng. Hãy gọi 1900 6750 để nhận báo giá cùng ưu đãi trong ngày.</p></details><details><summary>Lượng nước bạn cần uống khi massage?</summary><p>Nên uống đủ nước trước và sau liệu trình để hỗ trợ cơ thể đào thải và phục hồi tốt hơn.</p></details></div></div><img src="/theme-demo/spa111/hot-stone.png" alt="Câu hỏi thường gặp"></div></section>

    <section id="doi-ngu" class="sp11-team sp11-section xd-landing-block" data-landing-block-id="{{ data_get($team, 'id') }}" data-block-type="spa111_team"><div class="sp11-container"><div class="sp11-heading"><span class="sp11-eyebrow">NHÂN VIÊN TẬN TÂM</span><h2>Đội Ngũ Nhân Viên Chuyên Nghiệp</h2><p>Đội ngũ chuyên viên trị liệu được đào tạo bài bản, sở hữu kiến thức chuyên môn vững vàng và kinh nghiệm thực tiễn phong phú.</p></div><div class="sp11-team-grid">@foreach($teamItems->take(4) as $index => $item)<article><img src="{{ data_get($item,'image','/theme-demo/spa111/staff-minh-anh.png') }}" alt="{{ data_get($item,'title') }}"><div><span><b>{{ data_get($item,'title',data_get($item,'name','Chuyên viên')) }}</b><small>{{ data_get($item,'role',data_get($item,'summary','Chăm sóc sức khỏe')) }}</small></span><a href="tel:19006750"><i class="fa-solid fa-phone-volume"></i></a></div></article>@endforeach</div></div></section>

    <section id="tin-tuc" class="sp11-posts sp11-section xd-landing-block" data-landing-block-id="{{ data_get($posts, 'id') }}" data-block-type="spa111_latest_posts"><div class="sp11-container"><div class="sp11-heading"><span class="sp11-eyebrow">TIN TỨC MỚI NHẤT</span><h2>Xu Hướng Làm Đẹp &amp; Chăm Sóc Sức Khỏe</h2><p>Mang đến cho bạn những thông tin mới nhất về xu hướng làm đẹp giúp bạn luôn khỏe đẹp từ bên trong.</p></div><div class="sp11-post-grid">@foreach($postItems->take(4) as $item)<article><div><img src="{{ data_get($item,'image','/theme-demo/spa111/facial.png') }}" alt="{{ data_get($item,'title') }}"><time>{{ data_get($item,'date','10/01/2026') }}</time></div><h3>{{ data_get($item,'title') }}</h3><small>Đăng bởi: <b>Bean Spa</b></small><p>{{ \Illuminate\Support\Str::limit(strip_tags(data_get($item,'summary','')), 100) }}</p><a href="{{ data_get($item,'url','#') }}">Xem chi tiết <i class="fa-solid fa-arrow-up-right-from-square"></i></a></article>@endforeach</div></div></section>

    <section class="sp11-partners xd-landing-block" data-landing-block-id="{{ data_get($partners, 'id') }}" data-block-type="spa111_partners"><div class="sp11-container sp11-partner-grid"><div><span class="sp11-eyebrow">ĐỐI TÁC CHÚNG TÔI</span><h2>Tự Hào Là Đối Tác Tin Cậy</h2><p>Chúng tôi luôn trân trọng từng mối quan hệ hợp tác, xây dựng trên nền tảng niềm tin, chất lượng dịch vụ và giá trị bền vững.</p></div><div class="sp11-logo-grid">@forelse($partnerItems->take(9) as $item)<a href="{{ data_get($item,'url','#') }}">@if(data_get($item,'image'))<img src="{{ data_get($item,'image') }}" alt="{{ data_get($item,'title') }}">@else<strong>{{ data_get($item,'title',data_get($item,'name')) }}</strong>@endif</a>@empty @foreach(['JASMINE','CALMING SPA','CHRISTINE','SCARLET','BEAUTY SPA','ELEGANCE','SPA & BEAUTY','VIET NAILS','BEAUTY SALON'] as $name)<a href="#"><strong>{{ $name }}</strong></a>@endforeach @endforelse</div></div></section>

    <section id="lien-he" class="sp11-booking sp11-section xd-landing-block" data-landing-block-id="{{ data_get($booking, 'id') }}" data-block-type="spa111_booking"><div class="sp11-container sp11-booking-card"><form method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="contact"><span class="sp11-eyebrow">TƯ VẤN &amp; ĐẶT LỊCH MIỄN PHÍ</span><h2>Liên Hệ Bean Spa Để Được Chăm Sóc Tận Tâm</h2><p>Đội ngũ chuyên viên giàu kinh nghiệm luôn sẵn sàng lắng nghe, tư vấn chi tiết và đề xuất liệu trình phù hợp nhất.</p><div class="sp11-form-grid"><input name="name" placeholder="Họ và tên" required><input name="phone" placeholder="Điện thoại" required><input name="email" type="email" placeholder="Email" required><input placeholder="Địa chỉ"><select name="subject"><option value="">Chọn dịch vụ</option><option>Massage thư giãn</option><option>Chăm sóc da mặt</option><option>Massage đá nóng</option></select><input name="route_summary" type="date" value="{{ now()->format('Y-m-d') }}"><textarea name="message" minlength="10" placeholder="Nội dung" required></textarea></div><button class="sp11-btn">Gửi thông tin <i class="fa-solid fa-arrow-up-right-from-square"></i></button></form></div></section>
</main>
@endsection
