<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MainWebsiteTemplateSynchronizer
{
    public const ROOT_DOMAIN = 'demo.htvietnam.vn';

    private const CONNECTION = 'ht';

    private const TABLE = 'website_templates';

    private const MEDIA_TABLE = 'website_template_media';

    private const TRANSLATION_TABLE = 'website_template_translations';

    private const TRANSLATION_LANGUAGE_IDS = [1, 2];

    public function supports(string $rootDomain): bool
    {
        return $this->normalizeDomain($rootDomain) === self::ROOT_DOMAIN;
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $themes
     * @return array{inserted:int,updated:int,items:list<array{theme_code:string,action:string}>}
     */
    public function syncThemes(iterable $themes, string $rootDomain): array
    {
        if (! $this->supports($rootDomain)) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'items' => [],
            ];
        }

        $columns = collect(Schema::connection(self::CONNECTION)->getColumnListing(self::TABLE));
        $hasCodeColumn = $columns->contains('code');
        $result = [
            'inserted' => 0,
            'updated' => 0,
            'items' => [],
        ];

        foreach ($themes as $theme) {
            $themeCode = strtoupper(trim((string) ($theme['key'] ?? '')));

            if ($themeCode === '') {
                continue;
            }

            $action = DB::connection(self::CONNECTION)->transaction(
                function () use ($theme, $themeCode, $hasCodeColumn): string {
                    $now = now();
                    $existing = DB::connection(self::CONNECTION)
                        ->table(self::TABLE)
                        ->where(function ($query) use ($themeCode, $hasCodeColumn): void {
                            $query
                                ->where('theme_code', $themeCode)
                                ->orWhere('slug', $themeCode);

                            if ($hasCodeColumn) {
                                $query->orWhere('code', $themeCode);
                            }
                        })
                        ->first();
                    $values = [
                        'name' => $themeCode,
                        'slug' => $themeCode,
                        'theme_code' => $themeCode,
                        'base_price' => 199000,
                        'demo_url' => sprintf('https://%s.%s', strtolower($themeCode), self::ROOT_DOMAIN),
                        'current_version_number' => $this->versionNumber($theme['version'] ?? null),
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ];

                    if ($hasCodeColumn) {
                        $values['code'] = $themeCode;
                    }

                    $thumbnail = $this->thumbnailFileName($theme);

                    if ($thumbnail !== null) {
                        $values['preview_theme'] = $thumbnail;
                    }

                    if ($existing !== null) {
                        DB::connection(self::CONNECTION)
                            ->table(self::TABLE)
                            ->where('id', $existing->id)
                            ->update($values);
                        $templateId = (int) $existing->id;
                        $action = 'updated';
                    } else {
                        $templateId = (int) DB::connection(self::CONNECTION)
                            ->table(self::TABLE)
                            ->insertGetId([
                                ...$values,
                                'category_id' => null,
                                'created_at' => $now,
                            ]);
                        $action = 'inserted';
                    }

                    $this->syncTranslations($templateId, $themeCode, $now);

                    $thumbnailPath = $this->thumbnailPath($theme, $themeCode);

                    if ($thumbnailPath !== null) {
                        $this->syncThumbnailMedia($templateId, $themeCode, $thumbnailPath, $now);
                    }

                    return $action;
                },
            );

            $result[$action]++;
            $result['items'][] = [
                'theme_code' => $themeCode,
                'action' => $action,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function thumbnailFileName(array $theme): ?string
    {
        $thumbnail = trim((string) data_get($theme, 'preview.thumbnail', ''));

        if ($thumbnail === '') {
            $thumbnail = basename((string) parse_url(
                (string) data_get($theme, 'preview_urls.thumbnail', ''),
                PHP_URL_PATH,
            ));
        }

        if ($thumbnail === '') {
            return null;
        }

        return mb_substr(basename($thumbnail), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function thumbnailPath(array $theme, string $themeCode): ?string
    {
        $thumbnailUrl = trim((string) data_get($theme, 'preview_urls.thumbnail', ''));

        if ($thumbnailUrl !== '') {
            return mb_substr($thumbnailUrl, 0, 255);
        }

        $thumbnail = $this->thumbnailFileName($theme);

        if ($thumbnail === null) {
            return null;
        }

        return sprintf('/theme-previews/%s/%s', rawurlencode($themeCode), rawurlencode($thumbnail));
    }

    private function syncThumbnailMedia(
        int $templateId,
        string $themeCode,
        string $thumbnailPath,
        mixed $now,
    ): void {
        $existingMedia = DB::connection(self::CONNECTION)
            ->table(self::MEDIA_TABLE)
            ->where('template_id', $templateId)
            ->where('media_type', 'thumbnail')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
        $values = [
            'template_id' => $templateId,
            'media_type' => 'thumbnail',
            'file_path' => $thumbnailPath,
            'alt_text' => $themeCode.' thumbnail',
            'sort_order' => 0,
            'is_primary' => 1,
            'updated_at' => $now,
        ];

        if ($existingMedia !== null) {
            DB::connection(self::CONNECTION)
                ->table(self::MEDIA_TABLE)
                ->where('id', $existingMedia->id)
                ->update($values);

            return;
        }

        DB::connection(self::CONNECTION)
            ->table(self::MEDIA_TABLE)
            ->insert([
                ...$values,
                'created_at' => $now,
            ]);
    }

    private function syncTranslations(
        int $templateId,
        string $themeCode,
        mixed $now,
    ): void {
        foreach (self::TRANSLATION_LANGUAGE_IDS as $languageId) {
            $existingTranslation = DB::connection(self::CONNECTION)
                ->table(self::TRANSLATION_TABLE)
                ->where('template_id', $templateId)
                ->where('language_id', $languageId)
                ->first();
            $values = [
                'template_id' => $templateId,
                'language_id' => $languageId,
                'title' => $themeCode,
                'updated_at' => $now,
            ];

            if ($existingTranslation !== null) {
                DB::connection(self::CONNECTION)
                    ->table(self::TRANSLATION_TABLE)
                    ->where('id', $existingTranslation->id)
                    ->update($values);

                continue;
            }

            DB::connection(self::CONNECTION)
                ->table(self::TRANSLATION_TABLE)
                ->insert([
                    ...$values,
                    'created_at' => $now,
                ]);
        }
    }

    private function versionNumber(mixed $version): int
    {
        if (is_int($version)) {
            return max(0, $version);
        }

        if (preg_match('/^\s*(\d+)(?:\.(\d+))?(?:\.(\d+))?/', (string) $version, $matches) === 1) {
            $parts = array_map('intval', array_slice(array_pad($matches, 4, '0'), 1, 3));

            return max(1, array_sum($parts));
        }

        return 0;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('~[/:?#].*$~', '', $domain) ?? $domain;

        return trim($domain, '.');
    }
}
