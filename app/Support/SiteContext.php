<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteProfile;

class SiteContext
{
    public const DEFAULT_WEBSITE_KEY = 'website-main';

    private ?Site $site = null;

    private string $websiteKey = self::DEFAULT_WEBSITE_KEY;

    public function set(?Site $site, ?string $websiteKey = null): void
    {
        $this->site = $site;
        $this->websiteKey = $this->normalizeWebsiteKey($websiteKey ?: $site?->website_key);
    }

    public function site(): ?Site
    {
        return $this->site;
    }

    public function websiteKey(): string
    {
        return $this->websiteKey;
    }

    public function themeKey(): ?string
    {
        return $this->site?->theme_key ?: $this->profile()?->active_theme_key;
    }

    public function profile(): ?SiteProfile
    {
        return SiteProfile::query()
            ->withoutGlobalScope('current_website')
            ->where('website_key', $this->websiteKey)
            ->first();
    }

    public function normalizeWebsiteKey(?string $websiteKey): string
    {
        $websiteKey = trim((string) $websiteKey);

        return $websiteKey !== '' ? $websiteKey : self::DEFAULT_WEBSITE_KEY;
    }
}
