<?php

namespace App\Support;

use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Support\Localization\LocaleContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class CmsPostTags
{
    public function publicTags(string $websiteKey, string $locale, int $limit = 10): array
    {
        if (! self::available()) {
            return [];
        }

        $content = app(\App\Support\Localization\LocalizedContentRepository::class);

        return CmsTag::query()->where('website_key', $websiteKey)
            ->withCount(['posts' => fn (Builder $query) => $query
                ->where('website_key', $websiteKey)->where('status', 'published')
                ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))])
            ->orderByDesc('posts_count')->orderBy('id')->cursor()
            ->filter(fn (CmsTag $tag) => $tag->posts_count > 0
                && $content->isPublishedForLocale($tag, 'cms_tag', $locale, $websiteKey)
                && $this->publishedPosts($tag, $locale)->exists())
            ->take($limit)->map(function (CmsTag $tag) use ($content, $locale, $websiteKey): array {
                $tag = $content->localize($tag, 'cms_tag', $locale, $websiteKey);

                return ['name' => $tag->name, 'url' => FrontendRouteUrl::tag($tag->slug, $locale)];
            })->values()->all();
    }

    public static function available(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('cms_tags')
            && \Illuminate\Support\Facades\Schema::hasTable('cms_post_tag');
    }

    public function publishedPosts(CmsTag $tag, string $locale): Builder
    {
        $query = CmsPost::query()->where('website_key', $tag->website_key)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->whereHas('tags', fn ($q) => $q->where('cms_tags.id', $tag->id));
        $sourceLocale = app(LocaleContext::class)->sourceLocale();
        if ($locale !== $sourceLocale) {
            $query->whereExists(function ($q) use ($tag, $locale, $sourceLocale): void {
                $q->selectRaw('1')->from('content_translations as tag_post_translation')
                    ->whereColumn('tag_post_translation.resource_id', 'cms_posts.id')
                    ->where('tag_post_translation.resource_type', 'cms_post')
                    ->where('tag_post_translation.website_key', $tag->website_key)
                    ->where('tag_post_translation.locale', $locale)
                    ->where('tag_post_translation.translation_status', 'published')
                    ->where(function ($revision) use ($sourceLocale): void {
                        $revision->whereNull('tag_post_translation.source_revision')->orWhere('tag_post_translation.source_revision', '')
                            ->orWhereExists(function ($source) use ($sourceLocale): void {
                                $source->selectRaw('1')->from('content_translations as tag_post_source')
                                    ->whereColumn('tag_post_source.resource_id', 'tag_post_translation.resource_id')
                                    ->whereColumn('tag_post_source.website_key', 'tag_post_translation.website_key')
                                    ->where('tag_post_source.resource_type', 'cms_post')
                                    ->where('tag_post_source.locale', $sourceLocale)
                                    ->whereColumn('tag_post_source.translation_revision', 'tag_post_translation.source_revision');
                            });
                    });
            });
        }

        return $query;
    }

    public function sync(CmsPost $post, array $names): void
    {
        if (! self::available()) {
            if ($names !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tags' => 'Tính năng tags chưa sẵn sàng. Vui lòng chạy migration CMS tags trên máy chủ.',
                ]);
            }

            return;
        }

        $ids = [];
        foreach ($names as $name) {
            $name = Str::squish($name);
            if ($name === '') {
                continue;
            }
            $normalized = mb_strtolower($name);
            $websiteKey = $post->website_key;
            $identity = ['website_key' => $websiteKey, 'normalized_name' => $normalized];
            $slug = Str::slug($name) ?: 'tag';
            try {
                $tag = CmsTag::query()->firstOrCreate($identity, ['name' => $name, 'slug' => $slug]);
            } catch (UniqueConstraintViolationException $exception) {
                // Distinct names may transliterate to the same slug, including concurrent saves.
                $tag = CmsTag::query()->firstOrCreate($identity, [
                    'name' => $name,
                    'slug' => $slug.'-'.substr(hash('sha256', $normalized), 0, 12),
                ]);
            }
            $ids[] = $tag->id;
        }
        $post->tags()->sync(array_unique($ids));
    }
}
