<?php

namespace App\Support;

use App\Support\ThemeBlocks\Definitions\Ser0100ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Ser0101ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Th0001ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Lan0201ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Th0002ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Th0003ThemeBlockDefinition;
use App\Support\ThemeBlocks\Definitions\Th0020ThemeBlockDefinition;
use App\Support\ThemeBlocks\ThemeBlockDefinition;

class ThemeBlockRegistry
{
    /** @var array<string, ThemeBlockDefinition> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [
            'ser0100' => new Ser0100ThemeBlockDefinition(),
            'ser0101' => new Ser0101ThemeBlockDefinition(),
            'th0001' => new Th0001ThemeBlockDefinition(),
            'lan0201' => new Lan0201ThemeBlockDefinition(),
            'th0002' => new Th0002ThemeBlockDefinition(),
            'th0003' => new Th0003ThemeBlockDefinition(),
            'th0020' => new Th0020ThemeBlockDefinition(),
        ];
    }

    public function contentKey(string $themeKey, string $blockKey): string
    {
        return sprintf('theme_block.%s.%s', $this->normalizeThemeKey($themeKey), ltrim($blockKey, '.'));
    }

    /**
     * @return array<int, array{key:string,label:string,source_value:string}>
     */
    public function editableEntries(string $themeKey, string $websiteKey): array
    {
        $definition = $this->definitionFor($themeKey);

        if ($definition === null) {
            return [];
        }

        return array_map(
            static fn ($entry): array => $entry->toArray(),
            $definition->editableEntries($websiteKey, $this),
        );
    }

    /**
     * @return array<string, string>
     */
    public function legacyKeyMap(string $themeKey): array
    {
        return $this->definitionFor($themeKey)?->legacyKeyMap($this) ?? [];
    }

    private function normalizeThemeKey(?string $themeKey): string
    {
        return strtolower(trim((string) $themeKey));
    }
    private function definitionFor(string $themeKey): ?ThemeBlockDefinition
    {
        return $this->definitions[$this->normalizeThemeKey($themeKey)] ?? null;
    }
}
