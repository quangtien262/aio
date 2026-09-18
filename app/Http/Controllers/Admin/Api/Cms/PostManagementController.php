<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPost;
use App\Support\Cms\CmsPostWriter;
use App\Support\CmsPostTags;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostManagementController
{
    public function store(Request $request, CmsPostWriter $writer): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $post = $writer->create($validated);

        return response()->json(['message' => 'Đã tạo bài viết CMS.', 'data' => $this->serialize($post)], 201);
    }

    public function update(Request $request, int $post, CmsPostWriter $writer): JsonResponse
    {
        /** @var CmsPost $record */
        $record = CmsPost::query()->findOrFail($post);
        $validated = $this->validatePayload($request, $record);
        $record = $writer->update($record, $validated);

        return response()->json(['message' => 'Đã cập nhật bài viết CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_posts', 'id')],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cms_categories', 'id')],
            'is_highlight' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('category_id', $validated)) {
            $updates['category_id'] = $validated['category_id'];
        }

        if (array_key_exists('is_highlight', $validated)) {
            $updates['is_highlight'] = (bool) $validated['is_highlight'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Khong co thong tin can cap nhat.'], 422);
        }

        $count = CmsPost::query()
            ->whereIn('id', $validated['ids'])
            ->update($updates);

        return response()->json(['message' => 'Da cap nhat bai viet da chon.', 'data' => ['updated' => $count]]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_posts', 'id')],
        ]);

        $count = CmsPost::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Da xoa bai viet da chon.', 'data' => ['deleted' => $count]]);
    }

    public function destroy(Request $request, int $post): JsonResponse
    {
        /** @var CmsPost $record */
        $record = CmsPost::query()->findOrFail($post);
        $record->delete();

        return response()->json(['message' => 'Đã xóa bài viết CMS.']);
    }

    private function validatePayload(Request $request, ?CmsPost $post = null): array
    {
        $websiteKey = $post?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('cms_posts', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($post?->id),
            ],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'meta_keywords' => ['nullable', 'string', 'max:1000'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
            'publish_at' => ['nullable', 'date'],
            'is_highlight' => ['boolean'],
        ]);
    }

    private function serialize(CmsPost $post): array
    {
        return [
            'id' => $post->id,
            'tags' => CmsPostTags::available() ? $post->tags->pluck('name')->all() : [],
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'meta_keywords' => $post->meta_keywords,
            'publish_at' => $post->publish_at?->toAtomString(),
            'featured_media_id' => $post->featured_media_id,
            'category_id' => $post->category_id,
            'is_highlight' => $post->is_highlight,
        ];
    }
}
