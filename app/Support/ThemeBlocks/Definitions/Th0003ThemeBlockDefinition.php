<?php

namespace App\Support\ThemeBlocks\Definitions;

use App\Support\ThemeBlockRegistry;
use App\Support\ThemeBlocks\ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockEntry;

class Th0003ThemeBlockDefinition implements ThemeBlockDefinition
{
    public function themeKey(): string
    {
        return 'th0003';
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
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.eyebrow'), 'TH0003 / Hero / Nhan phu', 'Fashion studio'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.badge'), 'TH0003 / Hero / Badge', 'New season'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'hero_slide.cta'), 'TH0003 / Hero / CTA', 'Xem bo suu tap'),
        ];
    }

    private function latestPostEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.kicker'), 'TH0003 / Tin moi / Nhan phu', 'Fashion journal'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.title'), 'TH0003 / Tin moi / Tieu de', 'Tin moi tu shop'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'latest_posts.summary'), 'TH0003 / Tin moi / Mo ta', 'Cap nhat lookbook, cach phoi do va ghi chu van hanh moi nhat tu CMS.'),
        ];
    }

    private function footerEntries(ThemeBlockRegistry $registry): array
    {
        $defaults = [
            ['title' => 'Ho tro mua hang', 'links' => ['Chinh sach giao hang', 'Doi tra va bao hanh', 'Huong dan chon size', 'Theo doi don hang']],
            ['title' => 'Ve thuong hieu', 'links' => ['Ve chung toi', 'Lien he showroom', 'Chinh sach bao mat', 'Fashion journal']],
            ['title' => 'Bo suu tap', 'links' => ['Hang moi', 'San pham noi bat', 'Danh muc san pham', 'Tin tuc phoi do']],
        ];

        $entries = [];

        foreach ($defaults as $columnIndex => $column) {
            $entries[] = new ThemeBlockEntry(
                $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.title', $columnIndex)),
                sprintf('TH0003 / Footer / Cot %d / Tieu de', $columnIndex + 1),
                $column['title'],
            );

            foreach ($column['links'] as $linkIndex => $link) {
                $entries[] = new ThemeBlockEntry(
                    $registry->contentKey($this->themeKey(), sprintf('footer.columns.%d.links.%d', $columnIndex, $linkIndex)),
                    sprintf('TH0003 / Footer / Cot %d / Link %d', $columnIndex + 1, $linkIndex + 1),
                    $link,
                );
            }
        }

        return $entries;
    }

    private function companyFooterEntries(ThemeBlockRegistry $registry): array
    {
        return [
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'company_footer.address_line_1'), 'TH0003 / Cong ty / Dia chi 1', 'Showroom TH0003: 332 Luy Ban Bich, Tan Phu, TP.HCM'),
            new ThemeBlockEntry($registry->contentKey($this->themeKey(), 'company_footer.address_line_2'), 'TH0003 / Cong ty / Dia chi 2', 'Chi nhanh Ha Noi: Thanh Xuan - nhan tu van size, doi tra va pickup tai cua hang'),
        ];
    }
}
