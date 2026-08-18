<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Events\WebsiteLocalesChanged;
use App\Models\CmsPageTranslation;
use App\Models\ContentTranslation;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\LocalizedRoute;
use App\Models\SystemLocale;
use App\Models\ThemeTranslation;
use App\Models\WebsiteLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class WebsiteLocaleManager
{
    public function __construct(
        private readonly LocaleContext $localeContext,
        private readonly LocalizationReleaseReadiness $releaseReadiness,
    ) {}

    public function ensureSystemLocale(
        string $code,
        ?string $name = null,
        ?string $nativeName = null,
    ): SystemLocale {
        $code = LocaleCode::normalize($code);
        $preset = (array) config('localization.preset_locales.'.$code, []);

        $locale = SystemLocale::query()->firstOrNew(['code' => $code]);

        if (! $locale->exists) {
            $locale->name = $name ?: (string) ($preset['name'] ?? strtoupper($code));
            $locale->native_name = $nativeName ?? ($preset['native_name'] ?? null);
            $locale->is_default = false;
            $locale->is_active = false;
            $locale->is_published = false;
            $locale->sort_order = ((int) SystemLocale::query()->max('sort_order')) + 1;
        } else {
            if ($name !== null && trim($name) !== '') {
                $locale->name = $name;
            }

            if ($nativeName !== null) {
                $locale->native_name = $nativeName;
            }
        }

        $locale->save();

        return $locale->fresh();
    }

    public function provisionWebsite(string $websiteKey): void
    {
        if (! Schema::hasTable('website_locales') || ! Schema::hasTable('system_locales')) {
            return;
        }

        $websiteKey = trim($websiteKey) ?: 'website-main';

        if (WebsiteLocale::query()->forWebsite($websiteKey)->exists()) {
            return;
        }

        $sourceLocale = $this->localeContext->sourceLocale();
        $systemLocales = SystemLocale::query()
            ->where(function ($query) use ($sourceLocale): void {
                $query
                    ->where('is_default', true)
                    ->orWhere('is_active', true)
                    ->orWhere('code', $sourceLocale);
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->get();

        if ($systemLocales->isEmpty()) {
            return;
        }

        $defaultLocale = (string) ($systemLocales->firstWhere('is_default', true)?->code
            ?? $systemLocales->first()->code);
        $configuredFallback = LocaleCode::tryNormalize(
            (string) config('localization.fallback_locale', $defaultLocale),
        );
        $fallbackLocale = $configuredFallback !== null
            && $systemLocales->contains('code', $configuredFallback)
                ? $configuredFallback
                : $defaultLocale;

        DB::transaction(function () use (
            $websiteKey,
            $systemLocales,
            $defaultLocale,
            $fallbackLocale,
            $sourceLocale,
        ): void {
            foreach ($systemLocales as $systemLocale) {
                $isDefault = $systemLocale->code === $defaultLocale;
                $isSource = $systemLocale->code === $sourceLocale;

                WebsiteLocale::query()
                    ->withoutGlobalScope('current_website')
                    ->firstOrCreate(
                        [
                            'website_key' => $websiteKey,
                            'locale' => $systemLocale->code,
                        ],
                        [
                            'is_default' => $isDefault,
                            'is_enabled_for_editing' => $isDefault || $isSource || (bool) $systemLocale->is_active,
                            // A target locale must pass website-specific
                            // readiness before it becomes publicly routable.
                            'is_published' => $isDefault,
                            'fallback_locale' => $isDefault ? null : $fallbackLocale,
                            'sort_order' => (int) $systemLocale->sort_order,
                        ],
                    );
            }
        });

        $this->localeContext->flush($websiteKey);
        WebsiteLocalesChanged::dispatch($websiteKey);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function addLocale(string $websiteKey, string $code, array $attributes = []): WebsiteLocale
    {
        $websiteKey = trim($websiteKey) ?: 'website-main';
        $code = LocaleCode::normalize($code);
        $this->provisionWebsite($websiteKey);

        $systemLocale = SystemLocale::query()->where('code', $code)->firstOrFail();
        $existingCount = WebsiteLocale::query()->forWebsite($websiteKey)->count();

        $locale = WebsiteLocale::query()
            ->withoutGlobalScope('current_website')
            ->firstOrNew([
                'website_key' => $websiteKey,
                'locale' => $code,
            ]);

        if ($locale->exists) {
            throw ValidationException::withMessages([
                'code' => 'Ngôn ngữ này đã được thêm vào website.',
            ]);
        }

        $locale->is_default = $existingCount === 0;
        $isRequiredForEditing = $locale->is_default || $code === $this->localeContext->sourceLocale();
        $locale->is_enabled_for_editing = $isRequiredForEditing
            || (bool) ($attributes['is_enabled_for_editing'] ?? true);
        $locale->is_published = $locale->is_default
            || (bool) ($attributes['is_published'] ?? false);

        if ($locale->is_published && ! $locale->is_default && $code !== $this->localeContext->sourceLocale()) {
            $this->assertReleaseReady($websiteKey, $code);
        }
        $fallbackLocale = LocaleCode::tryNormalize(
            (string) ($attributes['fallback_locale'] ?? $this->localeContext->fallbackLocale($websiteKey)),
        );

        if ($locale->is_default && $fallbackLocale === $code) {
            $fallbackLocale = null;
        }

        if (
            $fallbackLocale === $code
            || ($fallbackLocale !== null
                && ! WebsiteLocale::query()->forWebsite($websiteKey)->where('locale', $fallbackLocale)->exists())
        ) {
            throw ValidationException::withMessages([
                'fallback_locale' => 'Ngôn ngữ fallback phải là một ngôn ngữ khác đã có trên website.',
            ]);
        }

        $locale->fallback_locale = $fallbackLocale;
        $locale->sort_order = $locale->exists
            ? (int) $locale->sort_order
            : ((int) WebsiteLocale::query()->forWebsite($websiteKey)->max('sort_order')) + 1;

        foreach (['domain', 'path_prefix', 'currency_code', 'timezone', 'date_format', 'number_format'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $locale->{$field} = $attributes[$field];
            }
        }

        $locale->save();

        $this->localeContext->flush($websiteKey);
        WebsiteLocalesChanged::dispatch($websiteKey);

        return $locale->fresh('systemLocale');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateLocale(string $websiteKey, string $code, array $attributes): WebsiteLocale
    {
        $websiteKey = trim($websiteKey) ?: 'website-main';
        $code = LocaleCode::normalize($code);
        $this->provisionWebsite($websiteKey);

        $locale = WebsiteLocale::query()
            ->forWebsite($websiteKey)
            ->where('locale', $code)
            ->firstOrFail();
        $sourceLocale = $this->localeContext->sourceLocale();

        if (
            array_key_exists('is_enabled_for_editing', $attributes)
            && $attributes['is_enabled_for_editing'] === false
            && ($locale->is_default || $locale->locale === $sourceLocale)
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'Không thể tắt ngôn ngữ nguồn hoặc ngôn ngữ mặc định của website.',
            ]);
        }

        if (
            array_key_exists('is_published', $attributes)
            && $attributes['is_published'] === false
            && $locale->is_default
        ) {
            throw ValidationException::withMessages([
                'is_published' => 'Không thể bỏ publish ngôn ngữ mặc định của website.',
            ]);
        }

        if (
            ($attributes['is_published'] ?? false) === true
            && ! $locale->is_published
            && ! $locale->is_default
            && $code !== $sourceLocale
        ) {
            $this->assertReleaseReady($websiteKey, $code);
        }

        if (
            ($attributes['is_default'] ?? false) === true
            && ! $locale->is_default
            && $code !== $sourceLocale
        ) {
            $this->assertReleaseReady($websiteKey, $code, 'is_default');
        }

        if (array_key_exists('fallback_locale', $attributes) && $attributes['fallback_locale'] !== null) {
            $fallbackLocale = LocaleCode::normalize((string) $attributes['fallback_locale']);

            if (
                $fallbackLocale === $code
                || ! WebsiteLocale::query()->forWebsite($websiteKey)->where('locale', $fallbackLocale)->exists()
            ) {
                throw ValidationException::withMessages([
                    'fallback_locale' => 'Ngôn ngữ fallback phải là một ngôn ngữ khác đã có trên website.',
                ]);
            }

            $attributes['fallback_locale'] = $fallbackLocale;
        }

        DB::transaction(function () use ($websiteKey, $locale, $attributes): void {
            if (($attributes['is_default'] ?? false) === true) {
                WebsiteLocale::query()
                    ->forWebsite($websiteKey)
                    ->whereKeyNot($locale->id)
                    ->update(['is_default' => false]);

                $locale->is_default = true;
                $locale->is_enabled_for_editing = true;
                $locale->is_published = true;
            }

            foreach ([
                'is_enabled_for_editing',
                'is_published',
                'fallback_locale',
                'sort_order',
                'domain',
                'path_prefix',
                'currency_code',
                'timezone',
                'date_format',
                'number_format',
            ] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $locale->{$field} = $field === 'fallback_locale'
                        ? LocaleCode::tryNormalize((string) $attributes[$field])
                        : $attributes[$field];
                }
            }

            if ($locale->is_default) {
                $locale->is_enabled_for_editing = true;
                $locale->is_published = true;
            }

            $locale->save();

            if (($attributes['is_published'] ?? null) === false) {
                $this->demotePublishedTranslations($websiteKey, $locale->locale);
            }
        });

        $this->localeContext->flush($websiteKey);
        WebsiteLocalesChanged::dispatch($websiteKey);

        return $locale->fresh('systemLocale');
    }

    private function assertReleaseReady(
        string $websiteKey,
        string $locale,
        string $field = 'is_published',
    ): void {
        $readiness = $this->releaseReadiness->report($websiteKey, [$locale])[$locale] ?? null;

        if (($readiness['ready'] ?? false) === true) {
            return;
        }

        throw ValidationException::withMessages([
            $field => sprintf(
                'Chưa thể publish %s: còn %d/%d nội dung chưa sẵn sàng.',
                $locale,
                (int) ($readiness['pending'] ?? 0),
                (int) ($readiness['required'] ?? 0),
            ),
        ]);
    }

    private function demotePublishedTranslations(string $websiteKey, string $locale): void
    {
        $attributes = [
            'translation_status' => TranslationStatus::Ready->value,
            'translation_published_at' => null,
        ];

        ContentTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $locale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->update($attributes);
        CmsPageTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $locale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->update($attributes);
        LandingPageData::query()
            ->withoutGlobalScopes()
            ->where('locale', $locale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->whereHas('landingPage', fn ($query) => $query->where('website_key', $websiteKey))
            ->update($attributes);
        LandingPageBlockData::query()
            ->withoutGlobalScopes()
            ->where('locale', $locale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->whereHas('landingPageBlock.landingPage', fn ($query) => $query->where('website_key', $websiteKey))
            ->update($attributes);
        ThemeTranslation::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $locale)
            ->where('translation_status', TranslationStatus::Published->value)
            ->update($attributes);
        LocalizedRoute::query()
            ->withoutGlobalScopes()
            ->where('website_key', $websiteKey)
            ->where('locale', $locale)
            ->update([
                'is_published' => false,
                'published_at' => null,
            ]);
    }
}
