<?php

namespace App\Support\Localization;

use Illuminate\Support\Str;
use Transliterator;

class LocalizedSlugGenerator
{
    public function normalize(
        mixed $value,
        string $locale,
        ?string $fallbackSlug = null,
        int $maxLength = 220,
    ): string {
        $locale = LocaleCode::tryNormalize($locale) ?? 'und';
        $value = trim((string) $value);
        $latin = $this->transliterate($value);
        $slug = Str::slug($latin);

        if ($slug === '') {
            $fallback = Str::slug($this->transliterate((string) $fallbackSlug));
            $slug = trim($fallback.'-'.Str::lower(str_replace('_', '-', $locale)), '-');
        }

        if ($slug === '') {
            $slug = 'content-'.Str::lower(str_replace('_', '-', $locale));
        }

        return Str::limit($slug, max(16, $maxLength), '');
    }

    /**
     * @param  callable(string): bool  $exists
     */
    public function unique(string $baseSlug, callable $exists, int $maxLength = 220): string
    {
        $baseSlug = Str::limit(trim($baseSlug, '-'), max(16, $maxLength), '');
        $candidate = $baseSlug;
        $suffix = 2;

        while ($exists($candidate)) {
            $tail = '-'.$suffix++;
            $candidate = Str::limit($baseSlug, max(1, $maxLength - strlen($tail)), '').$tail;
        }

        return $candidate;
    }

    private function transliterate(string $value): string
    {
        if ($value === '' || ! class_exists(Transliterator::class)) {
            return $value;
        }

        $transliterator = Transliterator::create(
            'Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC',
        );

        return $transliterator?->transliterate($value) ?: $value;
    }
}
