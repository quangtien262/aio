<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;

class Lan0201ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'lan0201';
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
        $legacy = [];

        foreach (['eyebrow', 'badge', 'cta'] as $field) {
            $legacy[$registry->contentKey('th0201', 'hero_slide.'.$field)] = $registry->contentKey($this->themeKey(), 'hero_slide.'.$field);
        }

        foreach (range(0, 2) as $columnIndex) {
            $legacy[$registry->contentKey('th0201', sprintf('footer.columns.%d.title', $columnIndex))] = $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex));

            foreach (range(0, 3) as $linkIndex) {
                $legacy[$registry->contentKey('th0201', sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex))] = $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex));
            }
        }

        foreach (['address_line_1', 'address_line_2'] as $field) {
            $legacy[$registry->contentKey('th0201', 'company_footer.'.$field)] = $registry->contentKey($this->themeKey(), 'company_footer.'.$field);
        }

        return $legacy;
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function heroSlideEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.eyebrow'), 'Khối theme / LAN0201 / Hero / Nhãn phụ', 'Landing dự án mở bán'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.badge'), 'Khối theme / LAN0201 / Hero / Badge', 'Nhận bảng giá, timeline và brochure mới nhất'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.cta'), 'Khối theme / LAN0201 / Hero / Nút CTA', 'Đăng ký nhận tư vấn'),
        ];
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function footerEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Tổng quan dự án', 'links' => ['Vị trí kết nối vùng', 'Masterplan & phân khu', 'Timeline mở bán', 'Tiến độ xây dựng']],
            ['title' => 'Bảng hàng & chính sách', 'links' => ['Giỏ hàng căn đẹp', 'Ưu đãi theo giai đoạn', 'Phương án thanh toán', 'FAQ dành cho nhà đầu tư']],
            ['title' => 'Kết nối sales gallery', 'links' => ['Đặt lịch xem nhà mẫu', 'Nhận brochure PDF', 'Liên hệ phòng kinh doanh', 'Chính sách bảo mật lead']],
        ];

        $entries = [];

        foreach ($defaults as $columnIndex => $column) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex)),
                sprintf('Khối theme / LAN0201 / Footer / Cột %d / Tiêu đề', $columnIndex + 1),
                $column['title'],
            );

            foreach ($column['links'] as $linkIndex => $link) {
                $entries[] = new ThemeBlockEntry(
                    $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex)),
                    sprintf('Khối theme / LAN0201 / Footer / Cột %d / Link %d', $columnIndex + 1, $linkIndex + 1),
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
                'Khối theme / LAN0201 / Thông tin công ty / Dòng địa chỉ 1',
                'Sales gallery: Đại lộ trung tâm dự án LAN0201, khu Đông thành phố',
            ),
            new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), 'company_footer.address_line_2'),
                'Khối theme / LAN0201 / Thông tin công ty / Dòng địa chỉ 2',
                'Private appointment lounge: Hotline nhận lịch hẹn, tư vấn tài chính và bảng hàng mở bán',
            ),
        ];
    }
}