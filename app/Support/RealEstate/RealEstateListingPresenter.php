<?php

namespace App\Support\RealEstate;

use App\Models\RealEstateListing;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;

class RealEstateListingPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function make(RealEstateListing $listing): array
    {
        $listing->loadMissing(['propertyType', 'media']);
        $media = $listing->media->map(fn ($item): array => [
            'id' => $item->id,
            'cms_media_id' => $item->cms_media_id,
            'media_type' => $item->media_type,
            'media_url' => $item->media_url,
            'alt_text' => $item->alt_text,
            'caption' => $item->caption,
            'is_featured' => (bool) $item->is_featured,
            'sort_order' => (int) $item->sort_order,
        ])->values();

        return [
            'id' => $listing->id,
            'property_type_id' => $listing->property_type_id,
            'property_type_name' => $listing->propertyType?->name,
            'cms_project_id' => $listing->cms_project_id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'code' => $listing->code,
            'publication_status' => $listing->publication_status,
            'availability_status' => $listing->availability_status,
            'transaction_type' => $listing->transaction_type,
            'price' => $listing->price !== null ? (float) $listing->price : null,
            'price_unit' => $listing->price_unit,
            'currency' => $listing->currency,
            'province' => $listing->province,
            'district' => $listing->district,
            'ward' => $listing->ward,
            'address' => $listing->address,
            'latitude' => $listing->latitude !== null ? (float) $listing->latitude : null,
            'longitude' => $listing->longitude !== null ? (float) $listing->longitude : null,
            'bedrooms' => $listing->bedrooms,
            'bathrooms' => $listing->bathrooms,
            'floors' => $listing->floors,
            'land_area' => $listing->land_area !== null ? (float) $listing->land_area : null,
            'floor_area' => $listing->floor_area !== null ? (float) $listing->floor_area : null,
            'direction' => $listing->direction,
            'legal_status' => $listing->legal_status,
            'furnishing_status' => $listing->furnishing_status,
            'video_url' => $listing->video_url,
            'virtual_tour_url' => $listing->virtual_tour_url,
            'summary' => $listing->summary,
            'content' => $listing->content,
            'meta_title' => $listing->meta_title,
            'meta_description' => $listing->meta_description,
            'meta_keywords' => $listing->meta_keywords,
            'is_featured' => (bool) $listing->is_featured,
            'is_hot' => (bool) $listing->is_hot,
            'sort_order' => (int) $listing->sort_order,
            'published_at' => $listing->published_at?->toIso8601String(),
            'expires_at' => $listing->expires_at?->toIso8601String(),
            'media' => $media->all(),
            'gallery_images' => $media->pluck('media_url')->filter()->values()->all(),
            'image_url' => $media->firstWhere('is_featured', true)['media_url']
                ?? $media->first()['media_url']
                ?? null,
            'public_url' => $listing->slug
                ? FrontendRouteUrl::realEstateListing($listing->slug, FrontendLocalization::defaultLocale())
                : null,
        ];
    }
}
