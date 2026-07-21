<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerManagementController
{
    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $partner = CmsPartner::query()->create(array_merge($payload, [
            'slug' => 'pending-partner-'.Str::uuid(),
        ]));
        $partner->update(['slug' => $this->uniqueSlug($partner->title, $partner->id)]);

        return response()->json(['message' => 'Da tao doi tac CMS.', 'data' => $this->serialize($partner->fresh())], 201);
    }

    public function update(Request $request, int $partner): JsonResponse
    {
        /** @var CmsPartner $record */
        $record = CmsPartner::query()->findOrFail($partner);
        $record->update($this->validatePayload($request, $record));
        $record->update(['slug' => $this->uniqueSlug($record->title, $record->id)]);

        return response()->json(['message' => 'Da cap nhat doi tac CMS.', 'data' => $this->serialize($record->fresh())]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_partners', 'id')],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('is_featured', $validated)) {
            $updates['is_featured'] = (bool) $validated['is_featured'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Khong co thong tin can cap nhat.'], 422);
        }

        $count = CmsPartner::query()
            ->whereIn('id', $validated['ids'])
            ->update($updates);

        return response()->json(['message' => 'Da cap nhat doi tac da chon.', 'data' => ['updated' => $count]]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_partners', 'id')],
        ]);

        $count = CmsPartner::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Da xoa doi tac da chon.', 'data' => ['deleted' => $count]]);
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
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'publish_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'website_key' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function uniqueSlug(string $title, int $id): string
    {
        $baseSlug = Str::slug($title) ?: 'doi-tac-'.$id;
        $exists = CmsPartner::query()
            ->where('slug', $baseSlug)
            ->whereKeyNot($id)
            ->exists();

        return $exists ? $baseSlug.'-'.$id : $baseSlug;
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
        ];
    }
}
