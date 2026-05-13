<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;

class Th0002ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'th0002';
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
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.eyebrow'), 'Khối theme / TH0002 / Hero / Nhãn phụ', 'Xưởng may theo yêu cầu'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.badge'), 'Khối theme / TH0002 / Hero / Badge', 'Từ 30 sản phẩm / mẫu'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.cta'), 'Khối theme / TH0002 / Hero / Nút CTA', 'Xem bộ sưu tập'),
        ];
    }

    /**
     * @return array<int, ThemeBlockEntry>
     */
    private function footerEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Hỗ trợ đặt may', 'links' => ['Quy trình báo giá', 'Hướng dẫn gửi techpack', 'MOQ và thời gian mẫu', 'Chính sách đổi trả']],
            ['title' => 'Về xưởng may', 'links' => ['Năng lực sản xuất', 'Dịch vụ OEM / ODM', 'Chất liệu và hoàn thiện', 'Liên hệ showroom']],
            ['title' => 'Hợp tác', 'links' => ['May đồng phục doanh nghiệp', 'Sản xuất cho local brand', 'Bán sỉ đại lý', 'Tuyển cộng tác viên']],
        ];

        $entries = [];

        foreach ($defaults as $columnIndex => $column) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex)),
                sprintf('Khối theme / TH0002 / Footer / Cột %d / Tiêu đề', $columnIndex + 1),
                $column['title'],
            );

            foreach ($column['links'] as $linkIndex => $link) {
                $entries[] = new ThemeBlockEntry(
                    $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex)),
                    sprintf('Khối theme / TH0002 / Footer / Cột %d / Link %d', $columnIndex + 1, $linkIndex + 1),
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
                'Khối theme / TH0002 / Thông tin công ty / Dòng địa chỉ 1',
                'Xưởng chính: Cụm công nghiệp may mặc, Tân Bình, TP.HCM',
            ),
            new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), 'company_footer.address_line_2'),
                'Khối theme / TH0002 / Thông tin công ty / Dòng địa chỉ 2',
                'Showroom tư vấn: Thanh Xuân, Hà Nội - nhận mẫu, duyệt size và chốt đơn sỉ lẻ',
            ),
        ];
    }
}
