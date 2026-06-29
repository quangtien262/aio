<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerManagementController
{
    public function store(Request $request): JsonResponse
    {
        $partner = CmsPartner::query()->create($this->validatePayload($request));

        return response()->json(['message' => 'Da tao doi tac CMS.', 'data' => $this->serialize($partner)], 201);
    }

    public function update(Request $request, int $partner): JsonResponse
    {
        /** @var CmsPartner $record */
        $record = CmsPartner::query()->findOrFail($partner);
        $record->update($this->validatePayload($request, $record));

        return response()->json(['message' => 'Da cap nhat doi tac CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function destroy(Request $request, int $partner): JsonResponse
    {
        /** @var CmsPartner $record */
        $record = CmsPartner::query()->findOrFail($partner);
        $record->delete();

        return response()->json(['message' => 'Da xoa doi tac CMS.']);
    }

    private function validatePayload(Request $request, ?CmsPartner $partner = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('cms_partners', 'slug')->ignore($partner?->id)],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'publish_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'owner_key' => ['nullable', 'string', 'max:255'],
            'tenant_key' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function serialize(CmsPartner $partner): array
    {
        return [
            'id' => $partner->id,
            'title' => $partner->title,
            'slug' => $partner->slug,
            'description' => $partner->description,
            'image_url' => $partner->image_url,
            'image_alt' => $partner->image_alt,
            'link_url' => $partner->link_url,
            'status' => $partner->status,
            'publish_at' => $partner->publish_at?->toAtomString(),
            'is_featured' => $partner->is_featured,
            'sort_order' => $partner->sort_order,
            'website_key' => $partner->website_key,
            'owner_key' => $partner->owner_key,
            'tenant_key' => $partner->tenant_key,
        ];
    }
}
