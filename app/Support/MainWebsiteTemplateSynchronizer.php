<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MainWebsiteTemplateSynchronizer
{
    public const ROOT_DOMAIN = 'demo.htvietnam.vn';

    private const CONNECTION = 'ht';

    private const TABLE = 'website_templates';

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
        $now = now();
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
                $action = 'updated';
            } else {
                DB::connection(self::CONNECTION)
                    ->table(self::TABLE)
                    ->insert([
                        ...$values,
                        'category_id' => null,
                        'created_at' => $now,
                    ]);
                $action = 'inserted';
            }

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
