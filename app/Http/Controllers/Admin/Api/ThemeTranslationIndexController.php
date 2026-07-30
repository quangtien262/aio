<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Themes\ThemeRegistry;
use App\Core\Themes\ThemeTranslationService;
use App\Models\SiteProfile;
use App\Support\BusinessContentTranslationService;
use App\Support\FrontendLocalization;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ThemeTranslationIndexController
{
    public function __invoke(string $key, Request $request, ThemeRegistry $themeRegistry, ThemeTranslationService $themeTranslationService, BusinessContentTranslationService $businessContentTranslationService): JsonResponse
    {
        abort_unless($themeRegistry->all()->contains(fn (array $theme): bool => $theme['key'] === $key), 404);

        $validated = $request->validate([
            'group' => ['nullable', 'string', Rule::in(['static', 'content'])],
            'keyword' => ['nullable', 'string', 'max:190'],
            'entity' => ['nullable', 'string', Rule::in($this->contentEntities())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $locale = FrontendLocalization::resolveEditableLocale((string) $request->query('locale', FrontendLocalization::defaultLocale()));
        $group = (string) ($validated['group'] ?? 'static');
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $entity = $group === 'content' ? (string) ($validated['entity'] ?? 'all') : 'all';
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $websiteKey = $this->resolveWebsiteKey();
        $entries = $group === 'content'
            ? $businessContentTranslationService->editableEntries($websiteKey, $locale, $key)
            : $themeTranslationService->editableEntries($key, $locale);

        $filteredEntries = $this->filterEntries(collect($entries), $keyword, $entity)->values();
        $total = $filteredEntries->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);
        $pagedEntries = $filteredEntries
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'theme_key' => $key,
                'locale' => $locale,
                'group' => $group,
                'keyword' => $keyword,
                'entity' => $entity,
                'available_groups' => ['static', 'content'],
                'supported_locales' => FrontendLocalization::editableLocales(),
                'locale_options' => FrontendLocalization::localeOptions(),
                'available_entities' => $group === 'content'
                    ? $this->contentEntities()
                    : ['all'],
                'entries' => $pagedEntries,
                'pagination' => [
                    'page' => $currentPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ],
        ]);
    }

    private function filterEntries(Collection $entries, string $keyword, string $entity): Collection
    {
        if ($entity !== 'all') {
            $entries = $entries->filter(fn (array $entry): bool => $this->entryMatchesEntity((string) ($entry['key'] ?? ''), $entity));
        }

        if ($keyword === '') {
            return $entries;
        }

        $normalizedKeyword = mb_strtolower($keyword);

        return $entries->filter(function (array $entry) use ($normalizedKeyword): bool {
            return collect([
                $entry['key'] ?? null,
                $entry['label'] ?? null,
                $entry['default_value'] ?? null,
                $entry['source_value'] ?? null,
                $entry['effective_value'] ?? null,
                $entry['override_value'] ?? null,
            ])
                ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                ->contains(fn (string $value): bool => mb_stripos($value, $normalizedKeyword) !== false);
        });
    }

    private function entryEntity(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'site_profile.'), str_starts_with($key, 'branding.') => 'site-profile',
            str_starts_with($key, 'cms_menu.') => 'menu',
            str_starts_with($key, 'site_banner.') => 'banner',
            str_starts_with($key, 'theme_block.'), str_starts_with($key, 'theme_metric.'), str_starts_with($key, 'theme_section.') => 'theme',
            str_starts_with($key, 'catalog_category.') => 'catalog-category',
            str_starts_with($key, 'catalog_product.') => 'catalog-product',
            str_starts_with($key, 'cms_page.') => 'cms-page',
            str_starts_with($key, 'cms_category.') => 'cms-category',
            str_starts_with($key, 'cms_post.') => 'cms-post',
            str_starts_with($key, 'cms_service.') => 'cms-service',
            str_starts_with($key, 'cms_project.') => 'cms-project',
            str_starts_with($key, 'cms_testimonial.') => 'cms-testimonial',
            str_starts_with($key, 'cms_team_member.') => 'cms-team-member',
            str_starts_with($key, 'cms_partner.') => 'cms-partner',
            default => 'all',
        };
    }

    private function entryMatchesEntity(string $key, string $entity): bool
    {
        $resolvedEntity = $this->entryEntity($key);

        return match ($entity) {
            'all' => true,
            'catalog' => in_array($resolvedEntity, ['catalog-category', 'catalog-product'], true),
            'cms' => in_array($resolvedEntity, ['cms-page', 'cms-category', 'cms-post', 'cms-service', 'cms-project', 'cms-testimonial', 'cms-team-member', 'cms-partner'], true),
            default => $resolvedEntity === $entity,
        };
    }

    /**
     * @return list<string>
     */
    private function contentEntities(): array
    {
        return [
            'all',
            'site-profile',
            'theme',
            'menu',
            'banner',
            'catalog',
            'catalog-category',
            'catalog-product',
            'cms',
            'cms-page',
            'cms-category',
            'cms-post',
            'cms-service',
            'cms-project',
            'cms-testimonial',
            'cms-team-member',
            'cms-partner',
        ];
    }

    private function resolveWebsiteKey(): string
    {
        $branding = SiteProfile::query()->value('branding');
        $decoded = is_array($branding) ? $branding : json_decode((string) $branding, true);

        return (string) data_get($decoded, 'website_key', app(SiteContext::class)->websiteKey());
    }
}
