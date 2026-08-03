<?php

namespace App\Console\Commands;

use App\Models\CmsMenu;
use App\Models\ContentTranslation;
use App\Models\Site;
use App\Models\SiteProfile;
use App\Support\Localization\LocalizationRollout;
use App\Support\SiteContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CmsMenuRolloutStatusCommand extends Command
{
    protected $signature = 'localization:menu-rollout-status
        {--website= : Website cần kiểm tra}
        {--theme= : Theme cần mô phỏng, mặc định lấy theme đang kích hoạt}
        {--json : Xuất báo cáo JSON}';

    protected $description = 'Kiểm tra reader Menu, đường rollback và dữ liệu bản dịch theo website/theme.';

    public function handle(
        LocalizationRollout $rollout,
        SiteContext $siteContext,
    ): int {
        $websiteKey = $siteContext->normalizeWebsiteKey(
            trim((string) $this->option('website'))
                ?: $siteContext->websiteKey(),
        );
        $themeKey = strtoupper(trim((string) $this->option('theme')));

        if ($themeKey === '') {
            $themeKey = $this->activeTheme($websiteKey);
        }

        $decision = $rollout->readerDecision(
            'cms_menu',
            $websiteKey,
            $themeKey !== '' ? $themeKey : null,
        );
        $translations = $this->translationSummary($websiteKey);
        $report = [
            'website_key' => $websiteKey,
            'theme_key' => $themeKey !== '' ? $themeKey : null,
            'reader' => config('localized-content.rollout.reader'),
            'menu_stage' => $decision['stage'],
            'new_reader_enabled' => $decision['enabled'],
            'decision_reason' => $decision['reason'],
            'dual_write' => $rollout->dualWriteEnabled(),
            'legacy_fallback' => $rollout->legacyFallbackEnabled(),
            'menu_count' => $this->menuCount($websiteKey),
            'translations' => $translations,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->info('CMS Menu localization rollout');
            $this->table(
                ['Website', 'Theme', 'Stage', 'Reader', 'Reason', 'Dual write', 'Legacy fallback'],
                [[
                    $websiteKey,
                    $themeKey !== '' ? $themeKey : '—',
                    $decision['stage'],
                    $decision['enabled'] ? 'new' : 'legacy',
                    $decision['reason'],
                    $rollout->dualWriteEnabled() ? 'on' : 'off',
                    $rollout->legacyFallbackEnabled() ? 'on' : 'off',
                ]],
            );
            $this->table(
                ['Locale', 'Status', 'Records'],
                collect($translations)
                    ->flatMap(fn (array $statuses, string $locale) => collect($statuses)
                        ->map(fn (int $count, string $status): array => [
                            $locale,
                            $status,
                            $count,
                        ])
                        ->values())
                    ->values()
                    ->all(),
            );
        }

        return $decision['reason'] === 'invalid_stage'
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function activeTheme(string $websiteKey): string
    {
        if (Schema::hasTable('sites')) {
            $siteTheme = Site::query()
                ->withoutGlobalScopes()
                ->where('website_key', $websiteKey)
                ->whereNotNull('theme_key')
                ->value('theme_key');

            if (filled($siteTheme)) {
                return strtoupper(trim((string) $siteTheme));
            }
        }

        if (! Schema::hasTable('site_profiles')) {
            return '';
        }

        return strtoupper(trim((string) SiteProfile::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->value('active_theme_key')));
    }

    private function menuCount(string $websiteKey): int
    {
        if (! Schema::hasTable('cms_menus')) {
            return 0;
        }

        return CmsMenu::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->count();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function translationSummary(string $websiteKey): array
    {
        if (! Schema::hasTable('content_translations')) {
            return [];
        }

        return ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('resource_type', 'cms_menu')
            ->get(['locale', 'translation_status'])
            ->groupBy('locale')
            ->map(fn ($translations): array => $translations
                ->countBy(fn (ContentTranslation $translation): string => (
                    $translation->translation_status->value
                ))
                ->all())
            ->all();
    }
}
