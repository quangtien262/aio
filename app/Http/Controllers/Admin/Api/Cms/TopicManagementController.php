<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsTopic;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TopicManagementController
{
    private function ensureAvailable(): void
    {
        abort_unless(CmsTopic::available(), 503, 'Chuyên đề chưa sẵn sàng. Vui lòng nâng cấp ứng dụng CMS để cập nhật cơ sở dữ liệu.');
    }

    public function index(Request $request, \App\Support\Localization\AdminLocalizedContentList $localizedList): JsonResponse
    {
        $this->ensureAvailable();
        $items = CmsTopic::query()->withCount('posts')->orderBy('name')->get()->toArray();
        $items = array_map(fn ($item) => [...$item, '_source' => $item], $items);
        return response()->json(['data' => ['items' => $localizedList->overlay($items, 'cms_topic', $request->query('locale'))]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAvailable();
        $topic = CmsTopic::create($this->validatePayload($request));
        return response()->json(['data' => $topic, 'message' => 'Đã tạo chuyên đề.'], 201);
    }

    public function update(Request $request, int $topic): JsonResponse
    {
        $this->ensureAvailable();
        $record = CmsTopic::findOrFail($topic);
        $record->update($this->validatePayload($request, $record));
        return response()->json(['data' => $record->fresh(), 'message' => 'Đã cập nhật chuyên đề.']);
    }

    public function destroy(int $topic): JsonResponse
    {
        $this->ensureAvailable();
        CmsTopic::findOrFail($topic)->delete();
        return response()->json(['message' => 'Đã xóa chuyên đề.']);
    }

    private function validatePayload(Request $request, ?CmsTopic $topic = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cms_topics', 'slug')->where('website_key', app(SiteContext::class)->websiteKey())->ignore($topic?->id)],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ], ['slug.unique' => 'Slug đã được dùng cho chuyên đề khác.']);
    }
}
