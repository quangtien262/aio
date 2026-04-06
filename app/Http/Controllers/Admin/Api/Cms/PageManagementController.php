<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $page = CmsPage::query()->create($validated);

        return response()->json([
            'message' => 'Đã tạo trang CMS.',
            'data' => $this->serializePage($page),
        ], 201);
    }

    public function update(Request $request, int $page): JsonResponse
    {
        /** @var CmsPage $record */
        $record = CmsPage::query()->findOrFail($page);
        $validated = $this->validatePayload($request, $record);

        $record->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật trang CMS.',
            'data' => $this->serializePage($record->fresh()),
        ]);
    }

    public function destroy(Request $request, int $page): JsonResponse
    {
        /** @var CmsPage $record */
        $record = CmsPage::query()->findOrFail($page);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa trang CMS.',
        ]);
    }

    private function validatePayload(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'template' => ['nullable', 'string', 'max:255'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'publish_at' => ['nullable', 'date'],
        ]);
    }

    private function serializePage(CmsPage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'excerpt' => $page->excerpt,
            'body' => $page->body,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'template' => $page->template,
            'featured_media_id' => $page->featured_media_id,
            'publish_at' => $page->publish_at?->toAtomString(),
        ];
    }
}
