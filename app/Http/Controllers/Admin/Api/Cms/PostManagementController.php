<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $post = CmsPost::query()->create($validated);

        return response()->json(['message' => 'Đã tạo bài viết CMS.', 'data' => $this->serialize($post)], 201);
    }

    public function update(Request $request, int $post): JsonResponse
    {
        /** @var CmsPost $record */
        $record = CmsPost::query()->findOrFail($post);
        $validated = $this->validatePayload($request, $record);
        $record->update($validated);

        return response()->json(['message' => 'Đã cập nhật bài viết CMS.', 'data' => $this->serialize($record->fresh())]);
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
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_posts', 'slug')->ignore($post?->id)],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
            'publish_at' => ['nullable', 'date'],
        ]);
    }

    private function serialize(CmsPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'status' => $post->status,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'publish_at' => $post->publish_at?->toAtomString(),
            'featured_media_id' => $post->featured_media_id,
            'category_id' => $post->category_id,
        ];
    }
}
