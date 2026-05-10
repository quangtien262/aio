<?php

namespace App\Support\ThemeBlocks;

use App\Support\ThemeBlockRegistry;

interface ThemeBlockDefinition
{
    public function themeKey(): string;

    /**
     * @return array<int, ThemeBlockEntry>
     */
    public function editableEntries(string $websiteKey, ThemeBlockRegistry $registry): array;

    /**
     * @return array<string, string>
     */
    public function legacyKeyMap(ThemeBlockRegistry $registry): array;
}
