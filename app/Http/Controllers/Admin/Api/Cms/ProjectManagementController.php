<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsProject;
use App\Models\CmsProjectImage;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $project = DB::transaction(function () use ($validated): CmsProject {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            /** @var CmsProject $project */
            $project = CmsProject::query()->create($validated);
            $this->syncImages($project, $images);

            return $project->load('images');
        });

        return response()->json(['message' => 'Da tao du an CMS.', 'data' => $this->serialize($project)], 201);
    }

    public function update(Request $request, int $project): JsonResponse
    {
        /** @var CmsProject $record */
        $record = CmsProject::query()->findOrFail($project);
        $validated = $this->validatePayload($request, $record);

        $record = DB::transaction(function () use ($record, $validated): CmsProject {
            $images = $validated['images'] ?? [];
            unset($validated['images']);

            $record->update($validated);
            $this->syncImages($record, $images);

            return $record->fresh('images');
        });

        return response()->json(['message' => 'Da cap nhat du an CMS.', 'data' => $this->serialize($record)]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_projects', 'id')],
            'cms_project_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cms_project_categories', 'id')],
            'is_featured' => ['sometimes', 'boolean'],
            'is_highlight' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        if (array_key_exists('cms_project_category_id', $validated)) {
            $updates['cms_project_category_id'] = $validated['cms_project_category_id'];
        }

        if (array_key_exists('is_featured', $validated)) {
            $updates['is_featured'] = (bool) $validated['is_featured'];
        }

        if (array_key_exists('is_highlight', $validated)) {
            $updates['is_highlight'] = (bool) $validated['is_highlight'];
        }

        if ($updates === []) {
            return response()->json(['message' => 'Khong co thong tin can cap nhat.'], 422);
        }

        $count = CmsProject::query()
            ->whereIn('id', $validated['ids'])
            ->update($updates);

        return response()->json(['message' => 'Da cap nhat du an da chon.', 'data' => ['updated' => $count]]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('cms_projects', 'id')],
        ]);

        $count = CmsProject::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => 'Da xoa du an da chon.', 'data' => ['deleted' => $count]]);
    }

    public function destroy(Request $request, int $project): JsonResponse
    {
        /** @var CmsProject $record */
        $record = CmsProject::query()->findOrFail($project);
        $record->delete();

        return response()->json(['message' => 'Da xoa du an CMS.']);
    }

    private function validatePayload(Request $request, ?CmsProject $project = null): array
    {
        $websiteKey = $project?->website_key ?: app(SiteContext::class)->websiteKey();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'cms_project_category_id' => ['nullable', 'integer', Rule::exists('cms_project_categories', 'id')],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cms_projects', 'slug')
                    ->where(fn ($query) => $query->where('website_key', $websiteKey))
                    ->ignore($project?->id),
            ],
            'status' => ['required', 'string', Rule::in(config('cms.workflow.statuses', ['draft', 'published']))],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'publish_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
            'is_highlight' => ['boolean'],
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
    private function syncImages(CmsProject $project, array $images): void
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

        $project->images()->delete();
        $normalizedImages->each(fn (array $image): CmsProjectImage => $project->images()->create($image));
    }

    private function serialize(CmsProject $project): array
    {
        $images = $project->images->map(fn (CmsProjectImage $image): array => [
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
            'id' => $project->id,
            'cms_project_category_id' => $project->cms_project_category_id,
            'category_name' => $project->category?->name,
            'title' => $project->title,
            'slug' => $project->slug,
            'status' => $project->status,
            'summary' => $project->summary,
            'content' => $project->content,
            'button_label' => $project->button_label,
            'link_url' => $project->link_url,
            'meta_title' => $project->meta_title,
            'meta_description' => $project->meta_description,
            'publish_at' => $project->publish_at?->toAtomString(),
            'is_featured' => $project->is_featured,
            'is_highlight' => $project->is_highlight,
            'sort_order' => $project->sort_order,
            'website_key' => $project->website_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
