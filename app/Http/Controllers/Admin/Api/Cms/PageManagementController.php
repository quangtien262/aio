<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Core\Access\AdminDataScope;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageManagementController
{
    public function store(Request $request, AdminDataScope $adminDataScope): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $page = CmsPage::query()->create($validated);

        return response()->json([
            'message' => 'Đã tạo trang CMS.',
            'data' => $this->serializePage($this->resolveScopedPage($request, $adminDataScope, $page->id)),
        ], 201);
    }

    public function update(Request $request, AdminDataScope $adminDataScope, int $page): JsonResponse
    {
        $record = $this->resolveScopedPage($request, $adminDataScope, $page);
        $validated = $this->validatePayload($request, $record);
        $this->ensureScopedPayloadAllowed($request, $validated);

        $record->update($validated);

        return response()->json([
            'message' => 'Đã cập nhật trang CMS.',
            'data' => $this->serializePage($record->fresh()),
        ]);
    }

    public function destroy(Request $request, AdminDataScope $adminDataScope, int $page): JsonResponse
    {
        $record = $this->resolveScopedPage($request, $adminDataScope, $page);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa trang CMS.',
        ]);
    }

    private function resolveScopedPage(Request $request, AdminDataScope $adminDataScope, int $pageId): CmsPage
    {
        $query = CmsPage::query();

        if ($admin = $request->user('admin')) {
            $adminDataScope->apply($query, $admin);
        }

        return $query->findOrFail($pageId);
    }

    private function validatePayload(Request $request, ?CmsPage $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_pages', 'slug')->ignore($page?->id)],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'body' => ['nullable', 'string'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
        ]);
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

    private function serializePage(CmsPage $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status,
            'body' => $page->body,
            'website_key' => $page->website_key,
            'owner_key' => $page->owner_key,
            'tenant_key' => $page->tenant_key,
        ];
    }
}
