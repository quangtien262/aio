<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsTestimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialManagementController
{
    public function store(Request $request): JsonResponse
    {
        $testimonial = CmsTestimonial::query()->create($this->validatePayload($request));

        return response()->json(['message' => 'Da tao nhan xet khach hang.', 'data' => $this->serialize($testimonial)], 201);
    }

    public function update(Request $request, int $testimonial): JsonResponse
    {
        /** @var CmsTestimonial $record */
        $record = CmsTestimonial::query()->findOrFail($testimonial);
        $record->update($this->validatePayload($request));

        return response()->json(['message' => 'Da cap nhat nhan xet khach hang.', 'data' => $this->serialize($record->fresh())]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_testimonials', 'id')],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('is_featured', $validated)) {
            $updates['is_featured'] = (bool) $validated['is_featured'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Khong co thong tin can cap nhat.'], 422);
        }

        $count = CmsTestimonial::query()
            ->whereIn('id', $validated['ids'])
            ->update($updates);

        return response()->json(['message' => 'Da cap nhat nhan xet da chon.', 'data' => ['updated' => $count]]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_testimonials', 'id')],
        ]);

        $count = CmsTestimonial::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Da xoa nhan xet da chon.', 'data' => ['deleted' => $count]]);
    }

    public function destroy(Request $request, int $testimonial): JsonResponse
    {
        /** @var CmsTestimonial $record */
        $record = CmsTestimonial::query()->findOrFail($testimonial);
        $record->delete();

        return response()->json(['message' => 'Da xoa nhan xet khach hang.']);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string'],
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

    private function serialize(CmsTestimonial $testimonial): array
    {
        return [
            'id' => $testimonial->id,
            'name' => $testimonial->name,
            'role' => $testimonial->role,
            'company' => $testimonial->company,
            'quote' => $testimonial->quote,
            'image_url' => $testimonial->image_url,
            'image_alt' => $testimonial->image_alt,
            'link_url' => $testimonial->link_url,
            'status' => $testimonial->status,
            'publish_at' => $testimonial->publish_at?->toAtomString(),
            'is_featured' => $testimonial->is_featured,
            'sort_order' => $testimonial->sort_order,
            'website_key' => $testimonial->website_key,
        ];
    }
}
