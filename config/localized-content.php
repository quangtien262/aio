<?php

use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsTeamMember;
use App\Models\CmsTestimonial;
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Models\SiteBanner;
use App\Models\SiteProfile;

return [
    'resources' => [
        'cms_category' => [
            'model' => CmsCategory::class,
            'fields' => ['name', 'slug', 'description', 'meta_title', 'meta_description'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.category.manage'],
            'publish_permissions' => ['cms.category.manage'],
        ],
        'cms_post' => [
            'model' => CmsPost::class,
            'fields' => ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description', 'meta_keywords'],
            'label_field' => 'title',
            'slug_field' => 'slug',
            'publication_field' => 'status',
            'view_permissions' => ['cms.post.view'],
            'update_permissions' => ['cms.post.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'cms_service_category' => [
            'model' => CmsServiceCategory::class,
            'fields' => ['name', 'slug', 'description', 'meta_title', 'meta_description'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'active_field' => 'is_active',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.update'],
        ],
        'cms_service' => [
            'model' => CmsService::class,
            'fields' => ['title', 'slug', 'summary', 'content', 'button_label', 'meta_title', 'meta_description', 'meta_keywords'],
            'label_field' => 'title',
            'slug_field' => 'slug',
            'publication_field' => 'status',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'cms_project_category' => [
            'model' => CmsProjectCategory::class,
            'fields' => ['name', 'slug', 'description', 'meta_title', 'meta_description'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'active_field' => 'is_active',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.update'],
        ],
        'cms_project' => [
            'model' => CmsProject::class,
            'fields' => ['title', 'slug', 'summary', 'content', 'button_label', 'meta_title', 'meta_description'],
            'label_field' => 'title',
            'slug_field' => 'slug',
            'publication_field' => 'status',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'catalog_category' => [
            'model' => CatalogCategory::class,
            'fields' => ['name', 'slug', 'description', 'meta_title', 'meta_description'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'active_field' => 'is_active',
            'view_permissions' => ['cms.product.view', 'catalog.view'],
            'update_permissions' => ['cms.product.update', 'catalog.update'],
            'publish_permissions' => ['cms.product.update', 'catalog.update'],
        ],
        'catalog_product' => [
            'model' => CatalogProduct::class,
            'fields' => ['name', 'slug', 'short_description', 'detail_content', 'meta_title', 'meta_description', 'meta_keywords', 'highlights', 'usage_terms', 'usage_location'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'active_field' => 'is_active',
            'view_permissions' => ['cms.product.view', 'catalog.view'],
            'update_permissions' => ['cms.product.update', 'catalog.update'],
            'publish_permissions' => ['cms.product.update', 'catalog.update'],
        ],
        'cms_team_member' => [
            'model' => CmsTeamMember::class,
            'fields' => ['name', 'slug', 'role', 'department', 'summary', 'bio'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'publication_field' => 'status',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'cms_partner' => [
            'model' => CmsPartner::class,
            'fields' => ['title', 'slug', 'description', 'image_alt'],
            'label_field' => 'title',
            'slug_field' => 'slug',
            'publication_field' => 'status',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'cms_testimonial' => [
            'model' => CmsTestimonial::class,
            'fields' => ['name', 'role', 'company', 'quote', 'image_alt'],
            'label_field' => 'name',
            'publication_field' => 'status',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.update'],
            'publish_permissions' => ['cms.publish'],
        ],
        'cms_menu' => [
            'model' => CmsMenu::class,
            'fields' => ['items'],
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.menu.manage'],
            'publish_permissions' => ['cms.menu.manage'],
        ],
        'site_banner' => [
            'model' => SiteBanner::class,
            'fields' => ['title', 'subtitle', 'badge', 'metadata'],
            'label_field' => 'title',
            'active_field' => 'is_active',
            'view_permissions' => ['catalog.view'],
            'update_permissions' => ['catalog.update'],
            'publish_permissions' => ['catalog.update'],
        ],
        'cms_media' => [
            'model' => CmsMedia::class,
            'fields' => ['title', 'alt_text'],
            'label_field' => 'title',
            'view_permissions' => ['cms.view'],
            'update_permissions' => ['cms.media.manage'],
            'publish_permissions' => ['cms.media.manage'],
        ],
        'site_profile' => [
            'model' => SiteProfile::class,
            'fields' => ['site_name', 'description', 'branding'],
            'label_field' => 'site_name',
            'view_permissions' => ['theme.view'],
            'update_permissions' => ['theme.customize'],
            'publish_permissions' => ['theme.customize'],
        ],
        'real_estate_listing' => [
            'model' => RealEstateListing::class,
            'fields' => ['title', 'slug', 'summary', 'content', 'meta_title', 'meta_description', 'meta_keywords'],
            'label_field' => 'title',
            'slug_field' => 'slug',
            'publication_field' => 'publication_status',
            'view_permissions' => ['real-estate.view'],
            'update_permissions' => ['real-estate.update'],
            'publish_permissions' => ['real-estate.update'],
        ],
        'real_estate_property_type' => [
            'model' => RealEstatePropertyType::class,
            'fields' => ['name', 'slug', 'description'],
            'label_field' => 'name',
            'slug_field' => 'slug',
            'active_field' => 'is_active',
            'view_permissions' => ['real-estate.view'],
            'update_permissions' => ['real-estate.type.manage'],
            'publish_permissions' => ['real-estate.type.manage'],
        ],
    ],
    'rollout' => [
        'reader' => env('LOCALIZATION_CONTENT_READER', 'new'),
        'dual_write' => env('LOCALIZATION_CONTENT_DUAL_WRITE', true),
        'legacy_fallback' => env('LOCALIZATION_CONTENT_LEGACY_FALLBACK', true),
        'stages' => [
            // Supported values: legacy, canary, all. The local/default
            // installation has completed the Menu rollout; production
            // deployments may start with canary without changing code.
            'cms_menu' => env('LOCALIZATION_MENU_ROLLOUT_STAGE', 'all'),
        ],
        'canaries' => [
            'cms_menu' => [
                'websites' => [],
                'themes' => ['BOOK920', 'DN302', 'BDS701'],
            ],
        ],
        'overrides' => [
            'cms_menu' => [
                // Website false is the emergency rollback switch and wins over
                // every theme setting for that website.
                'websites' => [],
                'themes' => [],
            ],
        ],
        'modules' => [
            'cms_category' => true,
            'cms_post' => true,
            'cms_service_category' => true,
            'cms_service' => true,
            'cms_project_category' => true,
            'cms_project' => true,
            'catalog_category' => true,
            'catalog_product' => true,
            'cms_team_member' => true,
            'cms_partner' => true,
            'cms_testimonial' => true,
            'cms_menu' => true,
            'site_banner' => true,
            'cms_media' => true,
            'site_profile' => true,
            'real_estate_listing' => true,
            'real_estate_property_type' => true,
        ],
        'websites' => [],
        'themes' => [],
    ],
];
