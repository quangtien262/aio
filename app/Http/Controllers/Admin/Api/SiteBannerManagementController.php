<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\SiteBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteBannerManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $record = SiteBanner::query()->create($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã tạo banner.',
            'data' => $this->serializeBanner($record),
        ], 201);
    }

    public function update(Request $request, int $banner): JsonResponse
    {
        $record = SiteBanner::query()->findOrFail($banner);
        $validated = $this->validatePayload($request);

        $record->update($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã cập nhật banner.',
            'data' => $this->serializeBanner($record->fresh()),
        ]);
    }

    public function destroy(int $banner): JsonResponse
    {
        $record = SiteBanner::query()->findOrFail($banner);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa banner.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'theme_key' => ['nullable', 'string', 'max:120'],
            'placement' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image_url' => ['required', 'url', 'max:2048'],
            'link_url' => ['nullable', 'string', 'max:2048'],
            'badge' => ['nullable', 'string', 'max:120'],
            'eyebrow' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizePayload(array $validated): array
    {
        return [
            'theme_key' => $validated['theme_key'] ?? null,
            'placement' => $validated['placement'],
            'title' => $validated['title'] ?? null,
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $validated['image_url'],
            'link_url' => $validated['link_url'] ?? null,
            'badge' => $validated['badge'] ?? null,
            'metadata' => array_filter([
                'eyebrow' => $validated['eyebrow'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'button_label' => $validated['button_label'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== ''),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function serializeBanner(SiteBanner $banner): array
    {
        return [
            'id' => $banner->id,
            'theme_key' => $banner->theme_key,
            'placement' => $banner->placement,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'image_url' => $banner->image_url,
            'link_url' => $banner->link_url,
            'badge' => $banner->badge,
            'eyebrow' => data_get($banner->metadata, 'eyebrow'),
            'summary' => data_get($banner->metadata, 'summary'),
            'button_label' => data_get($banner->metadata, 'button_label'),
            'sort_order' => $banner->sort_order,
            'is_active' => $banner->is_active,
        ];
    }
}
