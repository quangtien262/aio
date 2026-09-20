<?php

namespace App\Support\Cms;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Support\CmsPostTags;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CmsPostWriter
{
    public function __construct(private readonly CmsPostTags $tags) {}

    public function create(array $payload): CmsPost
    {
        $this->assertRelatedResources($payload);

        return DB::transaction(function () use ($payload): CmsPost {
            $attributes = $this->normalize($payload);
            $post = CmsPost::query()->create($attributes);
            $this->tags->sync($post, $payload['tags'] ?? []);
            if (\App\Models\CmsTopic::available()) $post->topics()->sync($payload['topic_ids'] ?? []);

            return $post->fresh($this->relations());
        });
    }

    public function update(CmsPost $post, array $payload): CmsPost
    {
        $this->assertRelatedResources($payload);

        return DB::transaction(function () use ($post, $payload): CmsPost {
            $post->update($this->normalize($payload, $post));
            if (array_key_exists('topic_ids', $payload) && \App\Models\CmsTopic::available()) $post->topics()->sync($payload['topic_ids']);
            if (array_key_exists('tags', $payload)) {
                $this->tags->sync($post, $payload['tags']);
            }

            return $post->fresh($this->relations());
        });
    }

    private function normalize(array $payload, ?CmsPost $post = null): array
    {
        $title = Str::squish((string) ($payload['title'] ?? $post?->title));
        $requestedSlug = trim((string) ($payload['slug'] ?? ''));
        $baseSlug = Str::slug($requestedSlug !== '' ? $requestedSlug : $title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'bai-viet'.($post ? '-'.$post->id : '');
        $metaTitle = $this->text($payload['meta_title'] ?? null) ?: $title;

        $attributes = [
            'title' => $title,
            'slug' => $this->uniqueSlug($baseSlug, $post?->id),
            'status' => (string) ($payload['status'] ?? $post?->status ?? 'draft'),
            'excerpt' => $this->text($payload['excerpt'] ?? null),
            'body' => $this->text($payload['body'] ?? null),
            'meta_title' => $metaTitle,
            'meta_description' => $this->text($payload['meta_description'] ?? null)
                ?: $this->text($payload['excerpt'] ?? null),
            'meta_keywords' => $this->text($payload['meta_keywords'] ?? null),
            'featured_media_id' => $payload['featured_media_id'] ?? null,
            'category_id' => $payload['category_id'] ?? null,
            'publish_at' => $payload['publish_at'] ?? null,
            'is_highlight' => (bool) ($payload['is_highlight'] ?? false),
        ];

        if ($post !== null) {
            foreach (['excerpt', 'body', 'meta_description', 'meta_keywords', 'featured_media_id', 'category_id', 'publish_at'] as $field) {
                if (! array_key_exists($field, $payload)) {
                    $attributes[$field] = $post->{$field};
                }
            }
            if (! array_key_exists('is_highlight', $payload)) {
                $attributes['is_highlight'] = $post->is_highlight;
            }
            if (! array_key_exists('meta_title', $payload)) {
                $attributes['meta_title'] = $post->meta_title ?: $title;
            }
            if (! array_key_exists('slug', $payload)) {
                $attributes['slug'] = $post->slug;
            }
        }

        return $attributes;
    }

    private function assertRelatedResources(array $payload): void
    {
        $errors = [];
        if (! empty($payload['topic_ids'])
            && (! \App\Models\CmsTopic::available() || \App\Models\CmsTopic::whereIn('id', $payload['topic_ids'])->count() !== count(array_unique($payload['topic_ids'])))) {
            $errors['topic_ids'] = 'Chuyên đề không thuộc website hiện tại.';
        }
        $websiteKey = app(SiteContext::class)->websiteKey();

        if (filled($payload['category_id'] ?? null)
            && ! CmsCategory::query()->whereKey($payload['category_id'])->exists()) {
            $errors['category_id'] = 'Danh mục không thuộc website '.$websiteKey.'.';
        }

        if (filled($payload['featured_media_id'] ?? null)
            && ! CmsMedia::query()->whereKey($payload['featured_media_id'])->exists()) {
            $errors['featured_media_id'] = 'Ảnh đại diện không thuộc website '.$websiteKey.'.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function uniqueSlug(string $baseSlug, ?int $ignoreId): string
    {
        $candidate = $baseSlug;
        $suffix = 2;

        while (CmsPost::query()
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function relations(): array
    {
        return CmsPostTags::available()
            ? ['category', 'featuredMedia', 'tags']
            : ['category', 'featuredMedia'];
    }
}
