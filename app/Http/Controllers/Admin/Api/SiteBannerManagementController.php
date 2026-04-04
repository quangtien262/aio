<?php

namespace App\Http\Controllers\Admin\Api;

use App\Core\Access\AdminDataScope;
use App\Models\SiteBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class SiteBannerManagementController
{
    public function store(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record = SiteBanner::query()->create($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã tạo banner.',
            'data' => $this->serializeBanner($this->resolveScopedBanner($request, $adminDataScope, $record->id)),
        ], 201);
    }

    public function update(Request $request, AdminDataScope $adminDataScope, int $banner): JsonResponse
    {
        $record = $this->resolveScopedBanner($request, $adminDataScope, $banner);
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record->update($this->normalizePayload($validated));

        return response()->json([
            'message' => 'Đã cập nhật banner.',
            'data' => $this->serializeBanner($record->fresh()),
        ]);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $banner): JsonResponse
    {
        $record = $this->resolveScopedBanner($request, $adminDataScope, $banner);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa banner.',
        ]);
    }

    private function resolveScopedBanner(Request $request, AdminDataScope $adminDataScope, int $bannerId): SiteBanner
    {
        $query = SiteBanner::query();

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        return $query->findOrFail($bannerId);
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
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
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
            'website_key' => $validated['website_key'] ?? null,
            'owner_key' => $validated['owner_key'] ?? null,
            'tenant_key' => $validated['tenant_key'] ?? null,
        ];
    }

    private function ensureScopedPayloadAllowed(Request $request, array $validated): void
    {
        $admin = $request->user('admin');

        if (! $admin) {
            return;
        }

        $scopeMatrix = $admin->scopeMatrix();

        foreach (['website' => 'website_key', 'owner' => 'owner_key', 'tenant' => 'tenant_key'] as $scopeType => $field) {
            $allowedValues = array_values(array_filter($scopeMatrix[$scopeType] ?? []));

            if ($allowedValues === []) {
                continue;
            }

            $value = Arr::get($validated, $field);

            if (! is_string($value) || $value === '' || ! in_array($value, $allowedValues, true)) {
                throw ValidationException::withMessages([
                    $field => ['Giá trị scope nằm ngoài phạm vi admin được cấp.'],
                ]);
            }
        }
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
            'website_key' => $banner->website_key,
            'owner_key' => $banner->owner_key,
            'tenant_key' => $banner->tenant_key,
        ];
    }
}
