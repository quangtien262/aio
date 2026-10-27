<?php

namespace App\Core\Themes\Demo;

use App\Models\LandingPage;
use App\Models\Site;
use App\Models\SiteProfile;

class Ser103HomepageDataRepair
{
    public function repairForWebsite(string $websiteKey): int
    {
        $site = Site::query()->where('website_key', $websiteKey)->first();
        $profile = SiteProfile::query()->withoutGlobalScopes()->where('website_key', $websiteKey)->first();
        if (($site?->theme_key ?: $profile?->active_theme_key) !== 'SER103' || ($site !== null && $site->status !== 'active')) {
            return 0;
        }

        $legacy = $this->legacyItems();
        $allowedHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $site !== null ? parse_url('https://'.$site->domain, PHP_URL_HOST) : null,
        ]);
        $repaired = 0;
        foreach (LandingPage::query()->where('website_key', $websiteKey)->where('theme_key', 'SER103')->where('is_home', true)->with('blocks.data')->get() as $page) {
            foreach ($page->blocks as $block) {
                $expected = $legacy[$block->block_type] ?? null;
                if ($expected === null || ($block->settings['source'] ?? '') !== 'custom') {
                    continue;
                }
                $sourceData = $block->data->firstWhere('locale', 'vi');
                $items = json_decode($sourceData?->content ?? '[]', true)['items'] ?? [];
                if (! is_array($items) || count($items) !== count($expected)) {
                    continue;
                }
                foreach ($items as $index => $item) {
                    if (! is_array($item) || ! isset($expected[$index])) {
                        continue 2;
                    }
                    $url = (string) ($item['url'] ?? '');
                    $oldUrl = $expected[$index]['url'];
                    unset($item['url'], $expected[$index]['url']);
                    $path = parse_url($url, PHP_URL_PATH);
                    $validUrl = $oldUrl === '#' ? $url === '#' : preg_match('~^/(?:[a-z]{2}(?:-[a-zA-Z0-9]+)?/)?'.ltrim($oldUrl, '/').'/?$~', $path ?? '');
                    $host = parse_url($url, PHP_URL_HOST);
                    if (($host !== null && ! in_array($host, $allowedHosts, true)) || filled(parse_url($url, PHP_URL_QUERY)) || ($oldUrl !== '#' && filled(parse_url($url, PHP_URL_FRAGMENT)))) {
                        continue 2;
                    }
                    if ($item != $expected[$index] || ! $validUrl) {
                        continue 2;
                    }
                }
                // Preserve manually authored items in other locales as well.
                foreach ($block->data->where('locale', '!=', 'vi') as $translation) {
                    if (! empty(json_decode($translation->content ?? '[]', true)['items'])) {
                        continue 2;
                    }
                }
                $block->update(['settings' => array_merge($block->settings ?? [], [
                    'source' => $block->block_type === 'latest_posts' ? 'cms_posts' : 'cms_services',
                    'limit' => $block->block_type === 'business_service_grid' ? 6 : count($items),
                    'featured_only' => false,
                ])]);
                $repaired++;
            }
        }

        return $repaired;
    }

    /** Frozen original demo items; do not use these as storefront fallback content. */
    private function legacyItems(): array
    {
        $preview = fn (string $name): string => '/theme-previews/SER103/'.$name;
        $services = [
            ['title' => 'Trang điểm cô dâu', 'summary' => 'Phong cách trang điểm trong trẻo, tôn nét riêng và bền đẹp suốt ngày cưới.', 'image' => $preview('service-makeup.webp'), 'url' => '/s'],
            ['title' => 'Chụp ảnh cưới', 'summary' => 'Kể câu chuyện tình yêu bằng những khung hình tự nhiên, tinh tế và giàu cảm xúc.', 'image' => $preview('service-photo.webp'), 'url' => '/s'],
            ['title' => 'Quay phóng sự cưới', 'summary' => 'Lưu giữ những khoảnh khắc chân thật nhất bằng ngôn ngữ điện ảnh hiện đại.', 'image' => $preview('service-video.webp'), 'url' => '/s'],
            ['title' => 'Thuê xe cưới', 'summary' => 'Bộ sưu tập xe cưới thanh lịch, trang trí đồng điệu với phong cách buổi lễ.', 'image' => $preview('service-car.webp'), 'url' => '/s'],
            ['title' => 'Trang trí & bàn ghế', 'summary' => 'Không gian tiệc cưới chỉn chu từ hoa, ánh sáng đến từng chi tiết bàn tiệc.', 'image' => $preview('service-banquet.webp'), 'url' => '/s'],
        ];
        $posts = [
            ['title' => 'Xu hướng các mẫu váy cưới đẹp nhất 2026', 'summary' => 'Những phom dáng hiện đại giúp cô dâu tỏa sáng theo cách riêng trong ngày trọng đại.', 'image' => $preview('news-minimal.webp'), 'date' => '18/11/2025', 'views' => 94, 'url' => '/c'],
            ['title' => 'Các mẫu váy cưới dẫn đầu xu hướng 2026', 'summary' => 'Từ chất liệu ánh ngọc đến đường cắt couture, đây là những lựa chọn đáng mong chờ.', 'image' => $preview('news-couture.webp'), 'date' => '18/11/2025', 'views' => 65, 'url' => '/c'],
            ['title' => 'Những kiểu váy cưới hot năm 2026', 'summary' => 'Vẻ đẹp tối giản và phom dáng thanh lịch tiếp tục chinh phục các cô dâu hiện đại.', 'image' => $preview('news-garden.webp'), 'date' => '18/11/2025', 'views' => 38, 'url' => '/c'],
        ];
        $gallery = [
            ['title' => 'Lễ cưới truyền thống', 'image' => $preview('gallery-aodai.webp'), 'url' => '#'],
            ['title' => 'Khoảnh khắc bên nhau', 'image' => $preview('service-photo.webp'), 'url' => '#'],
            ['title' => 'Tình yêu giữa thiên nhiên', 'image' => $preview('gallery-mountain.webp'), 'url' => '#'],
            ['title' => 'Ngày cưới thanh lịch', 'image' => $preview('about-fashion.webp'), 'url' => '#'],
            ['title' => 'Hoàng hôn hạnh phúc', 'image' => $preview('gallery-lake.webp'), 'url' => '#'],
        ];

        return ['business_service_grid' => $services, 'latest_posts' => $posts, 'collection_gallery' => $gallery];
    }
}
