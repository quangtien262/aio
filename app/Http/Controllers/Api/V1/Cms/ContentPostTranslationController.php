<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Enums\TranslationStatus;
use App\Models\CmsPost;
use App\Models\ContentApiResourceLink;
use App\Models\ContentApiToken;
use App\Models\ContentTranslation;
use App\Support\AuditLogger;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentPostTranslationController
{
    public function upsert(
        Request $request,
        string $externalId,
        string $locale,
        LocalizedContentRepository $repository,
        LocaleContext $localeContext,
        AuditLogger $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['required', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'publish' => ['nullable', 'boolean'],
            'is_machine_translated' => ['nullable', 'boolean'],
        ]);
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $websiteKey = app(SiteContext::class)->websiteKey();
        $locale = $localeContext->resolveEditable($locale, $websiteKey);

        if ($locale === $localeContext->sourceLocale()) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ nguồn phải được cập nhật qua API bài viết chính.',
            ]);
        }

        $publish = (bool) ($validated['publish'] ?? true);
        if (
            $publish
            && ! $token->hasAbility('translations.publish')
            && ! $token->hasAbility('posts.publish')
        ) {
            throw ValidationException::withMessages([
                'publish' => 'API token không có quyền xuất bản bản dịch trực tiếp.',
            ]);
        }

        $post = $this->resolvePost($token, $websiteKey, $externalId);
        if ($publish && $post->status !== 'published') {
            throw ValidationException::withMessages([
                'publish' => 'Bài viết nguồn phải được xuất bản trước khi xuất bản bản dịch.',
            ]);
        }

        $machineTranslated = (bool) ($validated['is_machine_translated'] ?? true);
        unset($validated['publish'], $validated['is_machine_translated']);

        $translation = DB::transaction(function () use (
            $repository,
            $websiteKey,
            $post,
            $locale,
            $validated,
            $machineTranslated,
            $publish,
        ): ContentTranslation {
            $translation = $repository->saveDraftPayload(
                $websiteKey,
                'cms_post',
                (string) $post->id,
                $locale,
                $validated,
                $machineTranslated,
                true,
            );

            if ($publish) {
                $translation = $repository->transition($translation, TranslationStatus::Ready);
                $translation = $repository->transition($translation, TranslationStatus::Published);
            }

            return $translation;
        });

        $audit->record('cms.content_api.post_translation.upserted', $translation, null, [
            'external_id' => $externalId,
            'source_key' => $token->source_key,
            'locale' => $locale,
            'translation_status' => $translation->translation_status->value,
            'is_machine_translated' => $translation->is_machine_translated,
        ], websiteKey: $websiteKey);

        return response()->json([
            'message' => $publish
                ? 'Đã lưu và xuất bản bản dịch bài viết.'
                : 'Đã lưu bản nháp dịch bài viết.',
            'data' => $this->serialize($repository, $post, $translation),
        ]);
    }

    public function show(
        Request $request,
        string $externalId,
        string $locale,
        LocalizedContentRepository $repository,
        LocaleContext $localeContext,
    ): JsonResponse {
        /** @var ContentApiToken $token */
        $token = $request->attributes->get('content_api_token');
        $websiteKey = app(SiteContext::class)->websiteKey();
        $locale = $localeContext->resolveEditable($locale, $websiteKey);

        if ($locale === $localeContext->sourceLocale()) {
            throw ValidationException::withMessages([
                'locale' => 'Ngôn ngữ nguồn không có bản dịch độc lập.',
            ]);
        }
        $post = $this->resolvePost($token, $websiteKey, $externalId);
        $translation = $repository->translation(
            $websiteKey,
            'cms_post',
            (string) $post->id,
            $locale,
            false,
        );

        abort_if($translation === null, 404);

        return response()->json([
            'data' => $this->serialize($repository, $post, $translation),
        ]);
    }

    private function resolvePost(ContentApiToken $token, string $websiteKey, string $externalId): CmsPost
    {
        $link = ContentApiResourceLink::query()->where([
            'source_key' => $token->source_key,
            'website_key' => $websiteKey,
            'resource_type' => 'cms_post',
            'external_id' => $externalId,
        ])->firstOrFail();

        return CmsPost::query()->findOrFail($link->resource_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(
        LocalizedContentRepository $repository,
        CmsPost $post,
        ContentTranslation $translation,
    ): array {
        $data = $repository->serialize($translation);
        $canonicalPath = $repository->publicCanonicalPath(
            $post,
            'cms_post',
            $translation->locale,
            $translation->website_key,
        );

        $data['canonical_path'] = $canonicalPath;
        $data['public_path'] = $canonicalPath === null
            ? null
            : '/'.$translation->locale.$canonicalPath;

        return $data;
    }
}
