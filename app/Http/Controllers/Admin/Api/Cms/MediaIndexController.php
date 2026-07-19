<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsMediaFolder;
use App\Support\SiteContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $showAll = $request->boolean('show_all') || $request->query('scope') === 'all';
        $siteContext = app(SiteContext::class);
        $currentWebsiteKey = $siteContext->websiteKey();
        $query = $showAll
            ? CmsMedia::query()->withoutGlobalScope('current_website')->latest()
            : CmsMedia::query()->latest();

        $mediaRecords = $query->get();
        $usageById = $this->usageByMediaId($mediaRecords->pluck('id')->all());

        $items = $mediaRecords->map(function (CmsMedia $media) use ($currentWebsiteKey, $usageById): array {
            $usageCount = $usageById[$media->id] ?? 0;

            return [
                'id' => $media->id,
                'title' => $media->title,
                'file_path' => $media->file_path,
                'file_url' => $media->file_url,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'alt_text' => $media->alt_text,
                'folder_path' => $media->folder_path,
                'website_key' => $media->website_key,
                'is_current_website' => $media->website_key === $currentWebsiteKey,
                'usage_count' => $usageCount,
                'is_unused' => $usageCount === 0,
            ];
        })->values()->all();

        $folderQuery = $showAll
            ? CmsMediaFolder::query()->withoutGlobalScope('current_website')
            : CmsMediaFolder::query();
        $folders = $folderQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'path', 'sort_order', 'website_key'])
            ->map(fn (CmsMediaFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'sort_order' => $folder->sort_order,
                'website_key' => $folder->website_key,
                'count' => collect($items)->where('folder_path', $folder->path)->count(),
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'folders' => $folders,
                'total' => count($items),
                'unused_total' => collect($items)->where('is_unused', true)->count(),
                'current_website_key' => $currentWebsiteKey,
                'show_all' => $showAll,
            ],
        ]);
    }

    /**
     * @param  list<int>  $mediaIds
     * @return array<int, int>
     */
    private function usageByMediaId(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return [];
        }

        $usage = [];

        foreach (['cms_pages', 'cms_posts'] as $table) {
            DB::table($table)
                ->whereIn('featured_media_id', $mediaIds)
                ->whereNotNull('featured_media_id')
                ->select('featured_media_id', DB::raw('count(*) as total'))
                ->groupBy('featured_media_id')
                ->get()
                ->each(function (object $row) use (&$usage): void {
                    $mediaId = (int) $row->featured_media_id;
                    $usage[$mediaId] = ($usage[$mediaId] ?? 0) + (int) $row->total;
                });
        }

        foreach (['cms_service_images', 'cms_project_images', 'cms_team_member_images'] as $table) {
            DB::table($table)
                ->whereIn('cms_media_id', $mediaIds)
                ->whereNotNull('cms_media_id')
                ->select('cms_media_id', DB::raw('count(*) as total'))
                ->groupBy('cms_media_id')
                ->get()
                ->each(function (object $row) use (&$usage): void {
                    $mediaId = (int) $row->cms_media_id;
                    $usage[$mediaId] = ($usage[$mediaId] ?? 0) + (int) $row->total;
                });
        }

        return $usage;
    }
}
