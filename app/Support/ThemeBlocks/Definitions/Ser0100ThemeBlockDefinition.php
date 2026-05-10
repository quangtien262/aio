<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsPost;
use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Ser0100ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'ser0100';
    }

    public function editableEntries(string $websiteKey, ThemeBlockRegistry $registry): array
    {
        return array_merge(
            $this->metricEntries($websiteKey, $registry),
            $this->quotePanelEntries($registry),
            $this->latestPostsEntries($registry),
        );
    }

    public function legacyKeyMap(ThemeBlockRegistry $registry): array
    {
        $map = [
            'theme_section.latest_posts.kicker' => $registry->contentKey($this->themeKey(), 'latest_posts.kicker'),
            'theme_section.latest_posts.title' => $registry->contentKey($this->themeKey(), 'latest_posts.title'),
            'theme_section.latest_posts.summary' => $registry->contentKey($this->themeKey(), 'latest_posts.summary'),
        ];

        for ($index = 0; $index < 4; $index += 1) {
            $map[sprintf('theme_metric.service_metrics.%d.value', $index)] = $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.value', $index));
            $map[sprintf('theme_metric.service_metrics.%d.suffix', $index)] = $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.suffix', $index));
            $map[sprintf('theme_metric.service_metrics.%d.label', $index)] = $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.label', $index));
        }

        return $map;
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function metricEntries(string $websiteKey, ThemeBlockRegistry $registry): array
    {
        $categoryCountQuery = CatalogCategory::query()->where('is_active', true);
        $productCountQuery = CatalogProduct::query()->where('is_active', true);
        $postCountQuery = CmsPost::query()->where('status', 'published');

        $this->applyWebsiteScope($categoryCountQuery, $websiteKey);
        $this->applyWebsiteScope($productCountQuery, $websiteKey);
        $this->applyWebsiteScope($postCountQuery, $websiteKey);

        $defaults = [
            ['value' => (string) max(12, $categoryCountQuery->count() * 2), 'suffix' => '+', 'label' => 'gói dịch vụ và tuyến tham khảo'],
            ['value' => (string) max(24, $productCountQuery->count()), 'suffix' => '+', 'label' => 'mẫu nội dung catalog đang hiển thị'],
            ['value' => (string) max(3, $postCountQuery->count()), 'suffix' => '+', 'label' => 'bài viết hướng dẫn và cẩm nang'],
            ['value' => '24', 'suffix' => '/7', 'label' => 'hỗ trợ lead demo trong giao diện'],
        ];

        $entries = [];

        foreach ($defaults as $index => $metric) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.value', $index)),
                sprintf('Khối theme / SER0100 / Chỉ số trang chủ / Mục %d / Giá trị', $index + 1),
                $metric['value'],
            );
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.suffix', $index)),
                sprintf('Khối theme / SER0100 / Chỉ số trang chủ / Mục %d / Hậu tố', $index + 1),
                $metric['suffix'],
            );
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('service_metrics.%d.label', $index)),
                sprintf('Khối theme / SER0100 / Chỉ số trang chủ / Mục %d / Mô tả', $index + 1),
                $metric['label'],
            );
        }

        return $entries;
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function quotePanelEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Loại xe phổ biến', 'summary' => '4 chỗ, 7 chỗ, 16 chỗ, 29 chỗ, 45 chỗ, shuttle, cargo nhẹ.'],
            ['title' => 'Thông tin cần có', 'summary' => 'Số khách, điểm đón, điểm đến, ngày đi và số điện thoại liên hệ.'],
            ['title' => 'Cam kết phản hồi', 'summary' => 'CTA xuất hiện xuyên suốt từ trang chủ, trang danh mục, trang chi tiết và bước gửi yêu cầu.'],
            ['title' => 'Kênh ưu tiên', 'summary' => 'Hotline, email, trang liên hệ và form lưu nhu cầu để điều phối viên gọi lại.'],
        ];

        $entries = [new ThemeBlockEntry(
            $registry->contentKey($this->themeKey(), 'quote_panel.badge'),
            'Khối theme / SER0100 / Báo giá trong ngày / Nhãn nổi bật',
            'Báo giá trong ngày',
        )];

        foreach ($defaults as $index => $item) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('quote_panel.items.%d.title', $index)),
                sprintf('Khối theme / SER0100 / Báo giá trong ngày / Mục %d / Tiêu đề', $index + 1),
                $item['title'],
            );
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('quote_panel.items.%d.summary', $index)),
                sprintf('Khối theme / SER0100 / Báo giá trong ngày / Mục %d / Mô tả', $index + 1),
                $item['summary'],
            );
        }

        return $entries;
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function latestPostsEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.kicker'), 'Khối theme / SER0100 / Tin mới / Nhãn phụ', 'Tin mới'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.title'), 'Khối theme / SER0100 / Tin mới / Tiêu đề', 'Tin mới'),
            new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), 'latest_posts.summary'),
                'Khối theme / SER0100 / Tin mới / Mô tả',
                'Khối này lấy bài viết mới nhất từ CMS. Sếp có thể đổi đoạn mô tả này trong phần nội dung website để phù hợp với từng preset hoặc thương hiệu.',
            ),
        ];
    }

    private function applyWebsiteScope(Builder $query, string $websiteKey): void
    {
        if (! Schema::hasColumn($query->getModel()->getTable(), 'website_key') || trim($websiteKey) === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($websiteKey): void {
            $builder->whereNull('website_key')->orWhere('website_key', $websiteKey);
        });
    }
}
