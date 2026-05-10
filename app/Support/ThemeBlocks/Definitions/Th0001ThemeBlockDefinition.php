<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;

class Th0001ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'th0001';
    }

    public function editableEntries(string $websiteKey, ThemeBlockRegistry $registry): array
    {
        return array_merge(
            $this->heroSlideEntries($registry),
            $this->footerEntries($registry),
            $this->companyFooterEntries($registry),
        );
    }

    public function legacyKeyMap(ThemeBlockRegistry $registry): array
    {
        return [];
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function heroSlideEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.eyebrow'), 'Khối theme / TH0001 / Hero / Nhãn phụ', 'Ưu đãi nổi bật'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.badge'), 'Khối theme / TH0001 / Hero / Badge giá', 'Khám phá ngay'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.cta'), 'Khối theme / TH0001 / Hero / Nút CTA', 'Xem ngay'),
        ];
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function footerEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Trợ giúp', 'links' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership']],
            ['title' => 'Giới thiệu', 'links' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động']],
            ['title' => 'Hợp tác', 'links' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí']],
        ];

        $entries = [];

        foreach ($defaults as $columnIndex => $column) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex)),
                sprintf('Khối theme / TH0001 / Footer / Cột %d / Tiêu đề', $columnIndex + 1),
                $column['title'],
            );

            foreach ($column['links'] as $linkIndex => $link) {
                $entries[] = new ThemeBlockEntry(
                    $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex)),
                    sprintf('Khối theme / TH0001 / Footer / Cột %d / Link %d', $columnIndex + 1, $linkIndex + 1),
                    $link,
                );
            }
        }

        return $entries;
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function companyFooterEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), 'company_footer.address_line_1'),
                'Khối theme / TH0001 / Thông tin công ty / Dòng địa chỉ 1',
                '332 Lũy Bán Bích, Phường Hòa Thạnh, Quận Tân Phú, TP.HCM',
            ),
            new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), 'company_footer.address_line_2'),
                'Khối theme / TH0001 / Thông tin công ty / Dòng địa chỉ 2',
                'Chi nhánh Hà Nội: Tầng 3, CT2 Ban Cơ Yếu Chính Phủ, Thanh Xuân',
            ),
        ];
    }
}
