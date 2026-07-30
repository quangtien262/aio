<?php

namespace App\Support\Localization;

use App\Models\CmsPage;
use App\Models\CmsPageTranslation;

final readonly class CmsPageResolution
{
    public function __construct(
        public CmsPage $page,
        public CmsPageTranslation $translation,
        public string $requestedLocale,
        public bool $usedFallback,
        public ?string $redirectPath = null,
    ) {}
}
