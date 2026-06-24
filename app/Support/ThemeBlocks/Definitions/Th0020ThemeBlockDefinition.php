<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;

class Th0020ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'th0020';
    }

    public function editableEntries(string $websiteKey, ThemeBlockRegistry $registry): array
    {
        unset($websiteKey);

        return array_merge(
            $this->heroSlideEntries($registry),
            $this->latestPostEntries($registry),
            $this->footerEntries($registry),
            $this->companyFooterEntries($registry),
        );
    }

    public function legacyKeyMap(ThemeBlockRegistry $registry): array
    {
        unset($registry);

        return [];
    }

    private function heroSlideEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.eyebrow'), 'TH0020 / Hero / Nhan phu', 'Interior studio'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.badge'), 'TH0020 / Hero / Badge', 'New living'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.cta'), 'TH0020 / Hero / CTA', 'Xem bo suu tap'),
        ];
    }

    private function latestPostEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.kicker'), 'TH0020 / Tin moi / Nhan phu', 'Interior journal'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.title'), 'TH0020 / Tin moi / Tieu de', 'Y tuong moi cho khong gian song'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.summary'), 'TH0020 / Tin moi / Mo ta', 'Cap nhat xu huong noi that, cach phoi vat lieu va goi y chon san pham tu CMS.'),
        ];
    }

    private function footerEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Ho tro mua hang', 'links' => ['Chinh sach giao hang', 'Doi tra va bao hanh', 'Tu van phoi khong gian', 'Theo doi don hang']],
            ['title' => 'Ve showroom', 'links' => ['Ve chung toi', 'Lien he showroom', 'Chinh sach bao mat', 'Interior journal']],
            ['title' => 'Bo suu tap noi that', 'links' => ['Hang moi', 'San pham noi bat', 'Danh muc san pham', 'Y tuong bai tri']],
        ];

        $entries = [];

        foreach ($defaults as $columnIndex => $column) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex)),
                sprintf('TH0020 / Footer / Cot %d / Tieu de', $columnIndex + 1),
                $column['title'],
            );

            foreach ($column['links'] as $linkIndex => $link) {
                $entries[] = new ThemeBlockEntry(
                    $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex)),
                    sprintf('TH0020 / Footer / Cot %d / Link %d', $columnIndex + 1, $linkIndex + 1),
                    $link,
                );
            }
        }

        return $entries;
    }

    private function companyFooterEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'company_footer.address_line_1'), 'TH0020 / Showroom / Dia chi 1', 'Showroom TH0020: 42 Nguyen Co Thach, Nam Tu Liem, Ha Noi'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'company_footer.address_line_2'), 'TH0020 / Showroom / Dia chi 2', 'Experience studio TP.HCM: nhan tu van phoi phong, vat lieu va giao lap theo lich hen'),
        ];
    }
}
