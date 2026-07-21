<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsTeamMember;
use App\Models\CmsTeamMemberImage;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamMemberManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $member = DB::transaction(function () use ($validated): CmsTeamMember {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            /** @var CmsTeamMember $member */
            $member = CmsTeamMember::query()->create($validated);
            $this->syncImages($member, $images);

            return $member->load('images');
        });

        return response()->json(['message' => 'Da tao nhan su CMS.', 'data' => $this->serialize($member)], 201);
    }

    public function update(Request $request, int $member): JsonResponse
    {
        /** @var CmsTeamMember $record */
        $record = CmsTeamMember::query()->findOrFail($member);
        $validated = $this->validatePayload($request, $record);

        $record = DB::transaction(function () use ($record, $validated): CmsTeamMember {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            $record->update($validated);
            $this->syncImages($record, $images);

            return $record->fresh('images');
        });

        return response()->json(['message' => 'Da cap nhat nhan su CMS.', 'data' => $this->serialize($record)]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_team_members', 'id')],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('is_featured', $validated)) {
            $updates['is_featured'] = (bool) $validated['is_featured'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Khong co thong tin can cap nhat.'], 422);
        }

        $count = CmsTeamMember::query()
            ->whereIn('id', $validated['ids'])
            ->update($updates);

        return response()->json(['message' => 'Da cap nhat nhan su da chon.', 'data' => ['updated' => $count]]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_team_members', 'id')],
        ]);

        $count = CmsTeamMember::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Da xoa nhan su da chon.', 'data' => ['deleted' => $count]]);
    }

    public function destroy(Request $request, int $member): JsonResponse
    {
        /** @var CmsTeamMember $record */
        $record = CmsTeamMember::query()->findOrFail($member);
        $record->delete();

        return response()->json(['message' => 'Da xoa nhan su CMS.']);
    }

    private function validatePayload(Request $request, ?CmsTeamMember $member = null): array
    {
        $websiteKey = $member?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cms_team_members', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($member?->id),
            ],
            'role' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'publish_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'website_key' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*.cms_media_id' => ['nullable', 'integer', Rule::exists('cms_media', 'id')],
            'images.*.image_url' => ['required_with:images', 'string', 'max:2048'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.caption' => ['nullable', 'string', 'max:255'],
            'images.*.is_featured' => ['boolean'],
            'images.*.sort_order' => ['nullable', 'integer'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $images
     */
    private function syncImages(CmsTeamMember $member, array $images): void
    {
        $normalizedImages = collect($images)
            ->filter(fn (array $image): bool => filled($image['image_url'] ?? null))
            ->values()
            ->map(function (array $image, int $index): array {
                return [
                    'cms_media_id' => $image['cms_media_id'] ?? null,
                    'image_url' => $image['image_url'],
                    'alt_text' => $image['alt_text'] ?? null,
                    'caption' => $image['caption'] ?? null,
                    'is_featured' => (bool) ($image['is_featured'] ?? false),
                    'sort_order' => (int) ($image['sort_order'] ?? $index),
                ];
            });

        if ($normalizedImages->isNotEmpty() && $normalizedImages->where('is_featured', true)->isEmpty()) {
            $first = $normalizedImages->shift();
            $first['is_featured'] = true;
            $normalizedImages->prepend($first);
        }

        $hasFeatured = false;
        $normalizedImages = $normalizedImages->map(function (array $image) use (&$hasFeatured): array {
            if ($image['is_featured'] && ! $hasFeatured) {
                $hasFeatured = true;

                return $image;
            }

            $image['is_featured'] = false;

            return $image;
        });

        $member->images()->delete();
        $normalizedImages->each(fn (array $image): CmsTeamMemberImage => $member->images()->create($image));
    }

    private function serialize(CmsTeamMember $member): array
    {
        $images = $member->images->map(fn (CmsTeamMemberImage $image): array => [
            'id' => $image->id,
            'cms_media_id' => $image->cms_media_id,
            'image_url' => $image->image_url,
            'alt_text' => $image->alt_text,
            'caption' => $image->caption,
            'is_featured' => $image->is_featured,
            'sort_order' => $image->sort_order,
        ])->values()->all();
        $featuredImage = collect($images)->firstWhere('is_featured', true) ?? ($images[0] ?? null);

        return [
            'id' => $member->id,
            'name' => $member->name,
            'slug' => $member->slug,
            'role' => $member->role,
            'department' => $member->department,
            'summary' => $member->summary,
            'bio' => $member->bio,
            'email' => $member->email,
            'phone' => $member->phone,
            'link_url' => $member->link_url,
            'status' => $member->status,
            'publish_at' => $member->publish_at?->toAtomString(),
            'is_featured' => $member->is_featured,
            'sort_order' => $member->sort_order,
            'website_key' => $member->website_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
