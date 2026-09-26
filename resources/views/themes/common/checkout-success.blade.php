@include('themes.common.catalog-shell-styles')


<main class="catalog-section"><div class="catalog-container" style="max-width:760px;text-align:center;padding-block:80px"><i class="fa-solid fa-circle-check" style="font-size:80px;color:#2ca56c"></i><h1>Đặt hàng thành công</h1><p>Cảm ơn bạn đã mua sắm tại {{ data_get($siteProfile, 'site_name') }}. Chúng tôi sẽ sớm liên hệ để xác nhận đơn hàng.</p><a class="catalog-more" href="{{ route('site.home') }}">Về trang chủ</a></div></main>
