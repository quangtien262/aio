<?php

namespace App\Http\Controllers\Admin\Api\Cms;

use App\Models\CmsMedia;
use App\Models\CmsTeamMember;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;

class TeamMemberIndexController
{
    public function __invoke(): JsonResponse
    {
        /** @var EloquentBuilder<CmsTeamMember> $query */
        $query = CmsTeamMember::query();
        $query->with(['images'])->orderBy('sort_order')->orderByDesc('updated_at');

        $items = $query->get()->map(fn (CmsTeamMember $member): array => $this->serialize($member))->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'metrics' => [
                    'published' => collect($items)->where('status', 'published')->count(),
                    'draft' => collect($items)->where('status', 'draft')->count(),
                    'featured' => collect($items)->where('is_featured', true)->count(),
                ],
                'media' => CmsMedia::query()
                    ->latest()
                    ->get(['id', 'title', 'file_path', 'file_url', 'alt_text'])
                    ->map(fn (CmsMedia $media): array => [
                        'id' => $media->id,
                        'title' => $media->title,
                        'file_url' => $media->file_url,
                        'alt_text' => $media->alt_text,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function serialize(CmsTeamMember $member): array
    {
        $images = $member->images->map(fn ($image): array => [
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
            'owner_key' => $member->owner_key,
            'tenant_key' => $member->tenant_key,
            'featured_image_url' => $featuredImage['image_url'] ?? null,
            'featured_image_alt' => $featuredImage['alt_text'] ?? null,
            'images' => $images,
        ];
    }
}
