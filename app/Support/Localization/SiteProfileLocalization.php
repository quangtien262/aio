<?php

namespace App\Support\Localization;

use App\Enums\TranslationStatus;
use App\Models\ContentTranslation;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;

class SiteProfileLocalization
{
    public const PROFILE_FIELDS = [
        'site_name',
        'description',
    ];

    public const BRANDING_FIELDS = [
        'company_name',
        'company_description',
        'slogan',
        'support_location',
        'copyright_text',
        'boc_footer_note',
    ];

    public function __construct(
        private readonly LocalizedContentRepository $localizedContent,
        private readonly LocaleContext $localeContext,
    ) {}

    public function localize(SiteProfile $profile, string $locale): SiteProfile
    {
        $websiteKey = (string) $profile->website_key;
        $resolvedLocale = $this->localeContext->resolvePublic($locale, $websiteKey);
        $translation = null;

        // The source profile is the live canonical record. A source-locale
        // translation is only a workflow snapshot and must never shadow newer
        // setup or active-theme branding changes.
        if ($resolvedLocale === $this->localeContext->sourceLocale()) {
            return $this->apply($profile, null);
        }

        foreach ($this->localeContext->fallbackChain($resolvedLocale, $websiteKey) as $candidate) {
            if ($candidate === $this->localeContext->sourceLocale()) {
                break;
            }

            $translation = $this->localizedContent->translation(
                $websiteKey,
                'site_profile',
                (string) $profile->getKey(),
                $candidate,
                true,
            );

            if ($translation !== null) {
                break;
            }
        }

        return $this->apply($profile, $translation);
    }

    public function localizeForEditor(SiteProfile $profile, string $locale): SiteProfile
    {
        $websiteKey = (string) $profile->website_key;
        $resolvedLocale = $this->localeContext->resolveEditable($locale, $websiteKey);

        if ($resolvedLocale === $this->localeContext->sourceLocale()) {
            return $this->apply($profile, null);
        }

        $translation = $this->localizedContent->translation(
            $websiteKey,
            'site_profile',
            (string) $profile->getKey(),
            $resolvedLocale,
            false,
        );
        $localized = $this->apply($profile, $translation);

        if ($translation === null) {
            foreach (self::PROFILE_FIELDS as $field) {
                $localized->setAttribute($field, '');
            }

            $branding = $profile->branding;
            foreach (self::BRANDING_FIELDS as $field) {
                $branding[$field] = '';
            }
            $localized->setLocalizedBranding($branding);
            $localized->setAttribute('translation_status', TranslationStatus::Missing);
        }

        return $localized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function savePublished(SiteProfile $profile, string $locale, array $payload): ContentTranslation
    {
        $branding = collect((array) ($payload['branding'] ?? []))
            ->only(self::BRANDING_FIELDS)
            ->all();
        $translationPayload = collect($payload)
            ->only(self::PROFILE_FIELDS)
            ->all();
        $translationPayload['branding'] = $branding;

        $translation = $this->localizedContent->saveDraftPayload(
            (string) $profile->website_key,
            'site_profile',
            (string) $profile->getKey(),
            FrontendLocalization::resolveEditableLocale($locale),
            $translationPayload,
            false,
            true,
        );
        $translation = $this->localizedContent->transition($translation, TranslationStatus::Ready);

        return $this->localizedContent->transition($translation, TranslationStatus::Published);
    }

    private function apply(SiteProfile $profile, ?ContentTranslation $translation): SiteProfile
    {
        $localized = clone $profile;
        $payload = (array) ($translation?->payload ?? []);

        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $localized->setAttribute($field, $payload[$field]);
            }
        }

        $branding = $profile->branding;
        $translatedBranding = (array) ($payload['branding'] ?? []);
        foreach (self::BRANDING_FIELDS as $field) {
            if (array_key_exists($field, $translatedBranding)) {
                $branding[$field] = $translatedBranding[$field];
            }
        }
        $localized->setLocalizedBranding($branding);

        if ($translation !== null) {
            $localized->setAttribute('resolved_locale', $translation->locale);
            $localized->setAttribute('translation_status', $translation->translation_status);
            $localized->setRelation('currentContentTranslation', $translation);
        }

        return $localized;
    }
}
