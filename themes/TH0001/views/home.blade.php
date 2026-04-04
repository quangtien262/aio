@php
    $heroSlides = [
        [
            'eyebrow' => 'Rạng rỡ mỗi ngày',
            'title' => 'Deal đẹp cho ẩm thực, spa và du lịch cuối tuần',
            'summary' => 'TH0001 dựng theo phong cách trang deal commerce: nhịp nhanh, nhiều ưu đãi, ưu tiên deal nổi bật và ngành hàng hot.',
            'badge' => 'Chỉ từ 149K',
            'cta' => 'Mua ngay',
            'image' => 'https://picsum.photos/seed/th0001-hero/960/520',
        ],
    ];

    $sidePromos = [
        ['title' => 'Chỉ với 89K', 'subtitle' => 'Combo thư giãn cuối tuần', 'image' => 'https://picsum.photos/seed/th0001-side-1/360/180'],
        ['title' => 'Du lịch Thái Lan', 'subtitle' => 'Flash sale 6.99M', 'image' => 'https://picsum.photos/seed/th0001-side-2/360/180'],
        ['title' => 'Deal làm đẹp', 'subtitle' => 'Ưu đãi mới mỗi ngày', 'image' => 'https://picsum.photos/seed/th0001-side-3/360/180'],
        ['title' => 'Quà xuân bình an', 'subtitle' => 'Voucher từ 85K', 'image' => 'https://picsum.photos/seed/th0001-side-4/360/180'],
    ];

    $brands = [
        ['name' => 'OLYMPIA', 'tone' => '#101828'],
        ['name' => 'GOLDEN MOON', 'tone' => '#8f5f00'],
        ['name' => 'NATURE BEAUTY', 'tone' => '#1c8c64'],
        ['name' => 'PREMIER PEARL', 'tone' => '#a66900'],
        ['name' => 'OLYMPIA FIT', 'tone' => '#0d9488'],
    ];

    $sidebarCategories = [
        [
            'label' => 'Khuyến mãi hot',
            'icon' => '🔥',
            'accent' => true,
            'panel_class' => 'mega-hot',
            'submenu' => [
                [
                    'title' => 'Deal nổi bật',
                    'items' => ['Bán chạy', 'Hàng mới', 'Giảm giá sốc'],
                ],
                [
                    'title' => 'Theo nhu cầu',
                    'items' => ['Ăn uống cuối tuần', 'Voucher thư giãn', 'Tour ngắn ngày', 'Combo gia đình'],
                ],
                [
                    'title' => 'Ưu đãi nhanh',
                    'items' => ['Flash sale 9h', 'Flash sale 12h', 'Mã freeship', 'Quà tặng thành viên'],
                ],
            ],
        ],
        [
            'label' => 'Ẩm thực',
            'icon' => '🍴',
            'panel_class' => 'mega-food',
            'submenu' => [
                [
                    'title' => 'Loại hình nổi bật',
                    'items' => ['Buffet', 'Café - Kem - Bánh', 'Ẩm thực Nhật - Hàn', 'Lẩu nướng', 'Nhà hàng gia đình'],
                ],
                [
                    'title' => 'Địa điểm đông khách',
                    'items' => ['Cầu Giấy', 'Ba Đình', 'Hoàn Kiếm', 'Hai Bà Trưng', 'Nhiều địa điểm'],
                ],
                [
                    'title' => 'Top deal ẩm thực',
                    'items' => ['Buffet tôm hùm', 'Grill & Chill', 'Set mẹt 4 người', 'Buffet chay cao cấp'],
                ],
            ],
        ],
        [
            'label' => 'Spa & Làm đẹp',
            'icon' => '💆',
            'panel_class' => 'mega-beauty',
            'submenu' => [
                [
                    'title' => 'Deal hot',
                    'items' => ['Bán chạy', 'Hàng mới', 'Giảm giá'],
                ],
                [
                    'title' => 'Dịch vụ làm đẹp',
                    'items' => ['Hair Salon & làm đẹp', 'Chăm sóc cơ thể', 'Thẩm mỹ trị liệu công nghệ cao', 'Phòng tập', 'Tắm trắng', 'Xem thêm'],
                ],
                [
                    'title' => 'Spa & Làm đẹp bán chạy',
                    'items' => ['Chăm sóc da mặt', 'Trị liệu thâm nám', 'Liệu trình tắm trắng', 'Liệu trình giảm cân', 'Trọn gói triệt lông', 'Trẻ hóa - nâng cơ da', 'Phun thêu thẩm mỹ', 'Chăm sóc nha khoa', 'Massage', 'Hair salon chăm sóc tóc', 'Xem thêm'],
                ],
                [
                    'title' => 'Top deal spa & làm đẹp',
                    'items' => ['Gội đầu dưỡng sinh', 'Massage cổ vai gáy', 'Triệt lông CN Laser', 'Bọc răng sứ'],
                ],
            ],
        ],
        ['label' => 'Giải trí & Thể thao', 'icon' => '🎯'],
        ['label' => 'Tour du lịch', 'icon' => '🧳'],
        ['label' => 'Buffet', 'icon' => '🍽'],
        ['label' => 'Massage Nam Nữ', 'icon' => '🧖'],
        ['label' => 'Nha khoa', 'icon' => '🦷'],
        ['label' => 'Bệnh viện & Phòng khám', 'icon' => '🏥'],
        ['label' => 'Hotel & Resort', 'icon' => '🏨'],
        ['label' => 'Đào tạo & Hội thảo', 'icon' => '🎓'],
    ];

    $featuredDeals = [
        ['title' => 'Nikko Hải Phòng - Weekend Buffet Dinner Với Tôm Hùm, Sashimi', 'price' => '933,000đ', 'old_price' => '1,097,712đ', 'discount' => '-15%', 'meta' => '12', 'image' => 'https://picsum.photos/seed/th0001-deal-1/520/360'],
        ['title' => 'Nikko Hải Phòng - Grill & Chill Với Hải Sản Nướng, Món Nhật', 'price' => '466,000đ', 'old_price' => '549,000đ', 'discount' => '-15%', 'meta' => '5', 'image' => 'https://picsum.photos/seed/th0001-deal-2/520/360'],
        ['title' => 'Sapa Catcat Hills Resort & Spa 4* - 2N1Đ Phòng Deluxe - 2 Khách', 'price' => '1,850,000đ', 'old_price' => '2,900,000đ', 'discount' => '-36%', 'meta' => '13', 'image' => 'https://picsum.photos/seed/th0001-deal-3/520/360'],
        ['title' => 'Embellir Spa - Gội Đầu Dưỡng Sinh + Massage Vai Gáy', 'price' => '99,000đ', 'old_price' => '300,000đ', 'discount' => '-67%', 'meta' => '16', 'image' => 'https://picsum.photos/seed/th0001-deal-4/520/360'],
    ];

    $sections = [
        [
            'theme' => 'lime',
            'title' => 'Ẩm thực',
            'tabs' => ['Mới nhất', 'Bán chạy', 'Giá tốt'],
            'filters' => ['Buffet', 'Café - Kem - Bánh', 'Ẩm thực Nhật - Hàn', 'Xem tất cả'],
            'items' => [
                ['title' => 'Nikko Hải Phòng - Weekend Buffet Dinner Với Tôm Hùm, Sashimi', 'price' => '933,000đ', 'old_price' => '1,097,712đ', 'discount' => '-15%', 'image' => 'https://picsum.photos/seed/th0001-food-1/640/420', 'tag' => 'E-voucher', 'meta' => '12'],
                ['title' => 'Nikko Hải Phòng - Grill & Chill Với Hải Sản Nướng, Món Nhật', 'price' => '466,000đ', 'old_price' => '549,000đ', 'discount' => '-15%', 'image' => 'https://picsum.photos/seed/th0001-food-2/640/420', 'tag' => 'E-voucher', 'meta' => '5'],
                ['title' => 'Thảo Nguyên Xanh - Set Lợn Mẹt / Lẩu Hơi Cho 4 Người', 'price' => '480,000đ', 'old_price' => '560,000đ', 'discount' => '-14%', 'image' => 'https://picsum.photos/seed/th0001-food-3/640/420', 'tag' => 'Cầu Giấy', 'meta' => '425'],
                ['title' => 'Nhà Hàng Chay Vô Vi - Buffet Chay 50 Món', 'price' => '129,000đ', 'old_price' => '150,000đ', 'discount' => '-14%', 'image' => 'https://picsum.photos/seed/th0001-food-4/640/420', 'tag' => 'Nhiều địa điểm', 'meta' => '17'],
                ['title' => 'Premier Pearl Vũng Tàu - Buffet Trưa Tại Sky Buffet', 'price' => '200,000đ', 'old_price' => '200,000đ', 'discount' => '-0%', 'image' => 'https://picsum.photos/seed/th0001-food-5/640/420', 'tag' => 'E-voucher', 'meta' => '15'],
                ['title' => 'Premier Pearl Vũng Tàu - Buffet Trà Chiều Dành Cho 2 Người', 'price' => '390,000đ', 'old_price' => '390,000đ', 'discount' => '-0%', 'image' => 'https://picsum.photos/seed/th0001-food-6/640/420', 'tag' => 'E-voucher', 'meta' => '9'],
                ['title' => 'Thảo Nguyên Xanh - Set Lẩu Hơi Cho 2 Người', 'price' => '299,000đ', 'old_price' => '380,000đ', 'discount' => '-21%', 'image' => 'https://picsum.photos/seed/th0001-food-7/640/420', 'tag' => 'Hot', 'meta' => '2,154'],
                ['title' => 'Ẩm Thực Chay Tuệ Biên Hòa - Buffet Chay Buổi Tối Cao Cấp', 'price' => '359,000đ', 'old_price' => '399,000đ', 'discount' => '-10%', 'image' => 'https://picsum.photos/seed/th0001-food-8/640/420', 'tag' => 'Biên Hòa', 'meta' => '6'],
            ],
        ],
        [
            'theme' => 'pink',
            'title' => 'Spa & Làm đẹp',
            'tabs' => ['Mới nhất', 'Bán chạy', 'Giá tốt'],
            'filters' => ['Hair salon & chăm sóc tóc', 'Điều trị da mặt', 'Massage body / foot', 'Xem tất cả'],
            'items' => [
                ['title' => 'Embellir Spa - Gội Đầu Dưỡng Sinh + Massage Vai Cổ Gáy', 'price' => '99,000đ', 'old_price' => '300,000đ', 'discount' => '-67%', 'image' => 'https://picsum.photos/seed/th0001-beauty-1/640/420', 'tag' => 'Ba Đình', 'meta' => '16'],
                ['title' => 'Golden Moon Spa - 3 Buổi Triệt Lông Nách / Mép', 'price' => '89,000đ', 'old_price' => '500,000đ', 'discount' => '-82%', 'image' => 'https://picsum.photos/seed/th0001-beauty-2/640/420', 'tag' => 'Cầu Giấy', 'meta' => '6'],
                ['title' => 'Nha Khoa Quốc Tế Việt Mỹ - Bọc Răng Sứ Venus', 'price' => '1,150,000đ', 'old_price' => '3,000,000đ', 'discount' => '-62%', 'image' => 'https://picsum.photos/seed/th0001-beauty-3/640/420', 'tag' => 'Ba Đình', 'meta' => '9'],
                ['title' => 'Thanh Hiền Luxury Spa - Triệt Lông CN Laser Diode 808NM', 'price' => '148,000đ', 'old_price' => '1,000,000đ', 'discount' => '-85%', 'image' => 'https://picsum.photos/seed/th0001-beauty-4/640/420', 'tag' => 'Hai Bà Trưng', 'meta' => '429'],
            ],
        ],
    ];

    $footerColumns = [
        'Trợ giúp' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership'],
        'Giới thiệu' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động'],
        'Hợp tác' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ data_get($siteProfile, 'site_name', 'TH0001 Deal Commerce') }}</title>
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-red-deep: #d91c20;
                --th-ink: #222222;
                --th-muted: #6d6d6d;
                --th-line: #e6e6e6;
                --th-bg: #f6f6f8;
                --th-surface: #ffffff;
                --th-green: #79c400;
                --th-pink: #ff4f92;
                --th-lime: #86c440;
                --th-orange: #ff8c1a;
                --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
            }

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .th-page { min-height: 100vh; }
            .th-topbar { background: #f3f3f3; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .th-topbar-inner { padding: 6px 0; }
            .th-inline { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
            .th-accent { color: var(--th-red); }
            .th-header { background: var(--th-surface); }
            .th-header-inner { padding: 12px 0; }
            .th-logo { font-size: 44px; line-height: 1; font-weight: 900; letter-spacing: -0.06em; color: var(--th-red); font-style: italic; }
            .th-logo small { font-size: 13px; background: var(--th-red); color: #fff; padding: 2px 6px; border-radius: 999px; vertical-align: super; margin-left: 4px; font-style: normal; }
            .th-search { flex: 1; display: grid; grid-template-columns: 220px 1fr 52px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; background: #fff; max-width: 720px; }
            .th-search select, .th-search input, .th-search button { border: 0; height: 44px; font-size: 14px; }
            .th-search select, .th-search input { padding: 0 14px; background: transparent; }
            .th-search select { border-right: 1px solid var(--th-line); color: #4f4f4f; }
            .th-search button { background: var(--th-red); color: #fff; font-weight: 700; cursor: pointer; }
            .th-cart { min-width: 120px; display: flex; justify-content: flex-end; font-weight: 700; color: #5f5f5f; }
            .th-main-nav { background: var(--th-red); color: #fff; }
            .th-main-nav-inner { min-height: 42px; }
            .th-main-nav-menu { display: flex; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; }
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 170px; padding: 11px 14px; font-weight: 700; }
            .th-content { padding: 0 0 40px; }
            .th-hero-layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; margin-top: 0; }
            .th-sidebar { position: relative; background: var(--th-surface); border: 1px solid var(--th-line); z-index: 5; }
            .th-sidebar-entry { position: static; }
            .th-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 14px; border-bottom: 1px solid var(--th-line); font-size: 14px; color: #4f4f4f; background: #fff; transition: background .16s ease, color .16s ease; }
            .th-sidebar-entry:last-child .th-sidebar-item { border-bottom: 0; }
            .th-sidebar-entry:hover .th-sidebar-item { color: var(--th-red); background: #fff7f7; }
            .th-sidebar-item.is-accent { color: var(--th-red); font-weight: 700; }
            .th-sidebar-icon { width: 20px; color: #979797; }
            .th-sidebar-mega { position: absolute; top: -1px; left: 100%; width: calc(100vw - max((100vw - 1200px) / 2, 12px) * 2 - 220px); max-width: 948px; min-height: 302px; display: grid; grid-template-columns: minmax(0, 1fr) 220px; background: #fff; border: 1px solid var(--th-line); box-shadow: 0 24px 48px rgba(21, 24, 34, 0.12); z-index: 8; opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(12px, 0, 0); transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-sidebar-mega::before { content: ''; position: absolute; top: 0; left: -20px; width: 20px; height: 100%; }
            .th-sidebar-entry:hover .th-sidebar-mega { opacity: 1; visibility: visible; pointer-events: auto; transform: translate3d(0, 0, 0); }
            .th-sidebar-mega-content { display: grid; grid-template-columns: 170px 1fr 1.15fr; gap: 34px; padding: 22px 26px 22px 24px; align-content: start; }
            .th-sidebar-mega-content.has-four .th-sidebar-mega-column:nth-child(4) { grid-column: 1 / 2; align-self: start; }
            .th-sidebar-mega.mega-hot { max-width: 920px; grid-template-columns: minmax(0, 1fr) 218px; }
            .th-sidebar-mega.mega-hot .th-sidebar-mega-content { grid-template-columns: 180px 1fr 1fr; gap: 30px; }
            .th-sidebar-mega.mega-food { max-width: 930px; grid-template-columns: minmax(0, 1fr) 220px; }
            .th-sidebar-mega.mega-food .th-sidebar-mega-content { grid-template-columns: 190px 190px 1fr; gap: 28px; }
            .th-sidebar-mega.mega-beauty { max-width: 968px; grid-template-columns: minmax(0, 1fr) 220px; }
            .th-sidebar-mega.mega-beauty .th-sidebar-mega-content { grid-template-columns: 140px 190px 1fr; gap: 28px 34px; }
            .th-sidebar-mega-column h4 { margin: 0 0 14px; font-size: 14px; line-height: 1.35; color: #1f1f1f; text-transform: uppercase; font-weight: 800; }
            .th-sidebar-mega-column ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
            .th-sidebar-mega-column a { color: #5f5f5f; font-size: 13px; line-height: 1.45; }
            .th-sidebar-mega-column a:hover { color: var(--th-red); }
            .th-sidebar-mega-promo { display: grid; gap: 8px; padding: 0; background: #fafafa; border-left: 1px solid var(--th-line); }
            .th-sidebar-mega-promo a { position: relative; min-height: 69px; overflow: hidden; }
            .th-sidebar-mega-promo img { width: 100%; height: 100%; object-fit: cover; }
            .th-sidebar-mega-promo span { position: absolute; left: 12px; bottom: 10px; right: 12px; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.45); }
            .th-hero-stack { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 12px; }
            .th-hero-card { background: linear-gradient(90deg, #fff3ea 0%, #fff 100%); min-height: 300px; position: relative; overflow: hidden; border: 1px solid #ffd7bd; }
            .th-hero-card img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .th-hero-overlay { position: relative; z-index: 1; width: min(54%, 420px); padding: 36px 32px; background: linear-gradient(90deg, rgba(255,244,236,0.95) 0%, rgba(255,255,255,0.2) 100%); height: 100%; }
            .th-eyebrow { display: inline-flex; padding: 6px 12px; border-radius: 999px; background: rgba(239,43,45,0.1); color: var(--th-red); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
            .th-hero-title { margin: 14px 0 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.05; color: #ff8c1a; }
            .th-hero-summary { margin: 0 0 20px; color: #7d675e; line-height: 1.6; }
            .th-hero-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .th-badge-price { background: #fff; color: var(--th-red); border-radius: 20px; padding: 10px 14px; font-size: 15px; font-weight: 800; box-shadow: 0 10px 24px rgba(239,43,45,0.14); }
            .th-hero-button { background: linear-gradient(180deg, #ff8e18 0%, #f25c05 100%); color: #fff; border-radius: 999px; padding: 11px 22px; font-weight: 800; text-transform: uppercase; }
            .th-side-promo-grid { display: grid; gap: 8px; }
            .th-side-promo { min-height: 69px; position: relative; overflow: hidden; border: 1px solid var(--th-line); }
            .th-side-promo img { width: 100%; height: 100%; object-fit: cover; }
            .th-side-promo span { position: absolute; left: 12px; bottom: 10px; z-index: 1; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.45); }
            .th-brand-strip { margin-top: 12px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 14px 16px; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
            .th-brand { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
            .th-brand-badge { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 900; font-size: 11px; box-shadow: var(--th-shadow); }
            .th-section-tabs { display: flex; gap: 24px; font-size: 14px; color: #7d7d7d; text-transform: uppercase; }
            .th-section-tabs span:first-child { color: var(--th-ink); font-weight: 800; }
            .th-featured-panel { margin-top: 22px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 0 0 22px; }
            .th-featured-topbar { padding: 0 16px; display: flex; align-items: center; gap: 24px; min-height: 48px; border-bottom: 1px solid var(--th-line); }
            .th-card-grid { padding: 18px 16px 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .th-deal-card { background: #fff; border: 1px solid var(--th-line); transition: transform .18s ease, box-shadow .18s ease; }
            .th-deal-card:hover { transform: translateY(-3px); box-shadow: var(--th-shadow); }
            .th-deal-image-wrap { position: relative; aspect-ratio: 1 / 1; overflow: hidden; background: #f1f1f1; }
            .th-deal-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
            .th-deal-chip, .th-deal-countdown { position: absolute; bottom: 10px; right: 10px; background: rgba(22,22,22,0.68); color: #fff; padding: 4px 8px; border-radius: 999px; font-size: 11px; }
            .th-deal-countdown { top: 10px; left: 10px; right: auto; bottom: auto; }
            .th-deal-body { padding: 12px 12px 14px; }
            .th-deal-title { margin: 0 0 12px; font-size: 15px; line-height: 1.45; min-height: 44px; }
            .th-pricing { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
            .th-price { color: var(--th-red); font-size: 31px; font-weight: 900; letter-spacing: -0.04em; }
            .th-price small { font-size: 18px; }
            .th-discount { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 6px; background: var(--th-red); color: #fff; font-size: 13px; font-weight: 800; }
            .th-old-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; color: #a8a8a8; font-size: 13px; }
            .th-old-price { text-decoration: line-through; }
            .th-stat { color: #9d9d9d; }
            .th-category-section { margin-top: 26px; background: var(--th-surface); border: 1px solid var(--th-line); }
            .th-category-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 16px; min-height: 52px; border-top: 4px solid var(--th-lime); }
            .th-category-header.pink { border-top-color: var(--th-pink); }
            .th-category-title { display: flex; align-items: center; gap: 12px; min-width: 220px; color: var(--th-lime); font-size: 28px; font-weight: 900; text-transform: uppercase; }
            .th-category-header.pink .th-category-title { color: var(--th-pink); }
            .th-category-title-badge { width: 32px; height: 32px; border-radius: 8px; background: currentColor; color: #fff; display: grid; place-items: center; font-size: 16px; }
            .th-category-filters, .th-category-tabs { display: flex; align-items: center; gap: 22px; font-size: 13px; color: #6f6f6f; flex-wrap: wrap; }
            .th-category-tabs span:first-child, .th-category-filters a:first-child { color: var(--th-ink); font-weight: 800; }
            .th-category-grid { padding: 16px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .th-category-footer { padding: 0 16px 18px; display: flex; justify-content: center; }
            .th-more-button { border: 1px solid var(--th-line); background: #fafafa; color: #6f6f6f; padding: 10px 18px; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }

            @media (max-width: 1100px) {
                .th-hero-layout { grid-template-columns: 1fr; }
                .th-sidebar { display: none; }
                .th-hero-stack { grid-template-columns: 1fr; }
                .th-card-grid, .th-category-grid, .th-brand-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .th-search { grid-template-columns: 160px 1fr 52px; }
                .th-sidebar-mega { display: none !important; opacity: 0 !important; visibility: hidden !important; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { text-align: center; font-size: 36px; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-search select { border-right: 0; border-bottom: 1px solid var(--th-line); }
                .th-main-nav-categories { min-width: 0; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-hero-overlay { width: 100%; padding: 24px 18px; }
                .th-card-grid, .th-category-grid, .th-brand-strip, .th-footer-grid { grid-template-columns: 1fr; }
                .th-category-header { align-items: flex-start; padding: 12px 16px; }
                .th-category-title { min-width: 0; font-size: 22px; }
                .th-category-tabs, .th-category-filters, .th-inline { gap: 12px; }
                .th-price { font-size: 28px; }
            }
        </style>
    </head>
    <body>
        <div class="th-page">
            <div class="th-topbar">
                <div class="th-container th-topbar-inner">
                    <div class="th-inline">
                        <span>📍 Hà Nội</span>
                        <span>📩 Đăng ký bản tin</span>
                    </div>
                    <div class="th-inline">
                        <span>📞 Hotline: <span class="th-accent">1900 6760 / 0354.466.968</span></span>
                        <span>✉ Email: cs@th0001.demo</span>
                        <span>Đăng ký</span>
                        <span>Đăng nhập</span>
                    </div>
                </div>
            </div>

            <header class="th-header">
                <div class="th-container th-header-inner">
                    <div class="th-logo">HOTDEAL<small>vn</small></div>
                    <div class="th-search">
                        <select>
                            <option>Tất cả danh mục</option>
                            <option>Ẩm thực</option>
                            <option>Spa & Làm đẹp</option>
                            <option>Du lịch</option>
                        </select>
                        <input type="text" placeholder="Tìm kiếm sản phẩm / khuyến mãi">
                        <button>Tìm</button>
                    </div>
                    <div class="th-cart">🛒 0 GIỎ HÀNG</div>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories">DANH MỤC</div>
                    <div class="th-main-nav-menu">
                        <a href="#featured">DEAL MỚI</a>
                        <a href="#featured">DEAL BÁN CHẠY</a>
                        <a href="#food">ẨM THỰC</a>
                        <a href="#beauty">SPA & LÀM ĐẸP</a>
                    </div>
                </div>
            </nav>

            <main class="th-content">
                <div class="th-container">
                    <section class="th-hero-layout">
                        <aside class="th-sidebar">
                            @foreach ($sidebarCategories as $category)
                                <div class="th-sidebar-entry">
                                    <a href="#" class="th-sidebar-item {{ !empty($category['accent']) ? 'is-accent' : '' }}">
                                        <span><span class="th-sidebar-icon">{{ $category['icon'] ?? '◌' }}</span> {{ $category['label'] }}</span>
                                        <span>›</span>
                                    </a>

                                    @if (!empty($category['submenu']))
                                        <div class="th-sidebar-mega {{ $category['panel_class'] ?? '' }}">
                                            <div class="th-sidebar-mega-content {{ count($category['submenu']) > 3 ? 'has-four' : '' }}">
                                                @foreach ($category['submenu'] as $column)
                                                    <div class="th-sidebar-mega-column">
                                                        <h4>{{ $column['title'] }}</h4>
                                                        <ul>
                                                            @foreach ($column['items'] as $item)
                                                                <li><a href="#">{{ $item }}</a></li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="th-sidebar-mega-promo">
                                                @foreach ($sidePromos as $promo)
                                                    <a href="#">
                                                        <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                                        <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </aside>

                        <div>
                            <div class="th-hero-stack">
                                <section class="th-hero-card">
                                    <img src="{{ $heroSlides[0]['image'] }}" alt="{{ $heroSlides[0]['title'] }}">
                                    <div class="th-hero-overlay">
                                        <span class="th-eyebrow">{{ $heroSlides[0]['eyebrow'] }}</span>
                                        <h1 class="th-hero-title">{{ $heroSlides[0]['title'] }}</h1>
                                        <p class="th-hero-summary">{{ $heroSlides[0]['summary'] }}</p>
                                        <div class="th-hero-actions">
                                            <span class="th-badge-price">{{ $heroSlides[0]['badge'] }}</span>
                                            <a href="#featured" class="th-hero-button">{{ $heroSlides[0]['cta'] }}</a>
                                        </div>
                                    </div>
                                </section>

                                <div class="th-side-promo-grid">
                                    @foreach ($sidePromos as $promo)
                                        <a href="#" class="th-side-promo">
                                            <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                            <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <section class="th-brand-strip">
                                @foreach ($brands as $brand)
                                    <div class="th-brand">
                                        <div class="th-brand-badge" style="background: {{ $brand['tone'] }}">{{ $brand['name'] }}</div>
                                        <strong>{{ $brand['name'] }}</strong>
                                    </div>
                                @endforeach
                            </section>
                        </div>
                    </section>

                    <section id="featured" class="th-featured-panel">
                        <div class="th-featured-topbar">
                            <div class="th-section-tabs">
                                <span>Deal nổi bật</span>
                                <span>Deal hôm nay</span>
                                <span>Deal dành cho bạn</span>
                            </div>
                        </div>

                        <div class="th-card-grid">
                            @foreach ($featuredDeals as $deal)
                                <article class="th-deal-card">
                                    <div class="th-deal-image-wrap">
                                        <img src="{{ $deal['image'] }}" alt="{{ $deal['title'] }}">
                                        <span class="th-deal-chip">E-voucher</span>
                                    </div>
                                    <div class="th-deal-body">
                                        <h3 class="th-deal-title">{{ $deal['title'] }}</h3>
                                        <div class="th-pricing">
                                            <span class="th-price">{{ $deal['price'] }}</span>
                                            <span class="th-discount">{{ $deal['discount'] }}</span>
                                        </div>
                                        <div class="th-old-price-row">
                                            <span class="th-old-price">{{ $deal['old_price'] }}</span>
                                            <span class="th-stat">👤 {{ $deal['meta'] }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    @foreach ($sections as $section)
                        <section id="{{ $loop->first ? 'food' : 'beauty' }}" class="th-category-section">
                            <div class="th-category-header {{ $section['theme'] === 'pink' ? 'pink' : '' }}">
                                <div class="th-category-title">
                                    <span class="th-category-title-badge">{{ $section['theme'] === 'pink' ? '✿' : '🍴' }}</span>
                                    <span>{{ $section['title'] }}</span>
                                </div>

                                <div class="th-category-tabs">
                                    @foreach ($section['tabs'] as $tab)
                                        <span>{{ $tab }}</span>
                                    @endforeach
                                </div>

                                <div class="th-category-filters">
                                    @foreach ($section['filters'] as $filter)
                                        <a href="#">{{ $filter }}</a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="th-category-grid">
                                @foreach ($section['items'] as $item)
                                    <article class="th-deal-card">
                                        <div class="th-deal-image-wrap">
                                            <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}">
                                            <span class="th-deal-countdown">⏱ Còn 21 ngày</span>
                                            <span class="th-deal-chip">{{ $item['tag'] }}</span>
                                        </div>
                                        <div class="th-deal-body">
                                            <h3 class="th-deal-title">{{ $item['title'] }}</h3>
                                            <div class="th-pricing">
                                                <span class="th-price">{{ $item['price'] }}</span>
                                                <span class="th-discount">{{ $item['discount'] }}</span>
                                            </div>
                                            <div class="th-old-price-row">
                                                <span class="th-old-price">{{ $item['old_price'] }}</span>
                                                <span class="th-stat">👤 {{ $item['meta'] }}</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="th-category-footer">
                                <a href="#" class="th-more-button">Xem tất cả {{ $section['title'] }} mới nhất</a>
                            </div>
                        </section>
                    @endforeach
                </div>
            </main>

            <footer class="th-footer">
                <div class="th-container th-footer-inner">
                    <div class="th-footer-grid">
                        @foreach ($footerColumns as $title => $links)
                            <section class="th-footer-card">
                                <h4>{{ $title }}</h4>
                                <div class="th-footer-links">
                                    @foreach ($links as $link)
                                        <a href="#">{{ $link }}</a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <section class="th-company">
                            <strong>CÔNG TY CỔ PHẦN TH0001 DEMO</strong>
                            <div class="th-footer-links">
                                <span>332 Lũy Bán Bích, Phường Hòa Thạnh, Quận Tân Phú, TP.HCM</span>
                                <span>Chi nhánh Hà Nội: Tầng 3, CT2 Ban Cơ Yếu Chính Phủ, Thanh Xuân</span>
                                <span>Hotline: 1900 6760 / 0354.466.968</span>
                                <span>Email: cs@th0001.demo</span>
                            </div>
                        </section>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
