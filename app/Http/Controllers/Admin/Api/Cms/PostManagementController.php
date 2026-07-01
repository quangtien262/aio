<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $post = CmsPost::query()->create($this->normalizePayload($validated));
        $post->update(['slug' => $this->uniqueSlug($post->title, $post->id)]);

        return response()->json(['message' => 'Đã tạo bài viết CMS.', 'data' => $this->serialize($post)], 201);
    }

    public function update(Request $request, int $post): JsonResponse
    {
        /** @var CmsPost $record */
        $record = CmsPost::query()->findOrFail($post);
        $validated = $this->validatePayload($request, $record);
        $record->update($this->normalizePayload($validated, $record));
        $record->update(['slug' => $this->uniqueSlug($record->title, $record->id)]);

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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('cms_posts', 'slug')->ignore($post?->id)],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
            'publish_at' => ['nullable', 'date'],
            'is_highlight' => ['boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, ?CmsPost $post = null): array
    {
        $title = trim((string) $validated['title']);
        $excerpt = $this->normalizeTextBlock($validated['excerpt'] ?? null);

        return [
            ...$validated,
            'title' => $title,
            'slug' => $post?->slug ?: 'pending-post-'.Str::lower((string) Str::uuid()),
            'excerpt' => $excerpt,
            'body' => $this->normalizeTextBlock($validated['body'] ?? null),
            'meta_title' => $this->normalizeTextBlock($validated['meta_title'] ?? null) ?: $title,
            'meta_description' => $this->normalizeTextBlock($validated['meta_description'] ?? null) ?: $excerpt,
            'is_highlight' => (bool) ($validated['is_highlight'] ?? false),
        ];
    }

    private function normalizeTextBlock(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function uniqueSlug(string $title, int $id): string
    {
        $baseSlug = Str::slug($title) ?: 'bai-viet-'.$id;

        $exists = CmsPost::query()
            ->where('slug', $baseSlug)
            ->whereKeyNot($id)
            ->exists();

        return $exists ? $baseSlug.'-'.$id : $baseSlug;
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
            'is_highlight' => $post->is_highlight,
        ];
    }
}
