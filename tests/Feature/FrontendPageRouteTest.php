<?php

namespace Tests\Feature;

use App\Support\FrontendRouteUrl;
use Tests\TestCase;

class FrontendPageRouteTest extends TestCase
{
    public function test_cms_pages_use_the_canonical_prefixed_route(): void
    {
        $this->assertSame('/p/gioi-thieu', FrontendRouteUrl::pagePath('gioi-thieu'));
        $this->assertSame('/vi/p/gioi-thieu', FrontendRouteUrl::page('gioi-thieu', 'vi', false));
    }

    public function test_all_common_storefront_links_are_generated_from_named_routes(): void
    {
        $this->assertSame('/san-pham/sofa', FrontendRouteUrl::productPath('sofa'));
        $this->assertSame('/danh-muc/noi-that', FrontendRouteUrl::categoryPath('noi-that'));
        $this->assertSame('/c/cam-nang', FrontendRouteUrl::blogCategoryPath('cam-nang'));
        $this->assertSame('/n/cach-chon-sofa', FrontendRouteUrl::postPath('cach-chon-sofa'));
        $this->assertSame('/s/thiet-ke', FrontendRouteUrl::serviceCategoryPath('thiet-ke'));
        $this->assertSame('/ser/thiet-ke-phong-ngu', FrontendRouteUrl::servicePath('thiet-ke-phong-ngu'));
        $this->assertSame('/pj/da-hoan-thanh', FrontendRouteUrl::projectCategoryPath('da-hoan-thanh'));
        $this->assertSame('/prj/biet-thu-ven-ho', FrontendRouteUrl::projectPath('biet-thu-ven-ho'));
        $this->assertSame('/contact', FrontendRouteUrl::contactPath());
    }

    public function test_localized_dynamic_links_preserve_query_fragment_and_external_urls(): void
    {
        $this->assertSame(
            '/vi/contact?source=menu#form',
            FrontendRouteUrl::localized('/contact?source=menu#form', 'vi', false)
        );
        $this->assertSame(
            'https://example.com/path',
            FrontendRouteUrl::localized('https://example.com/path', 'vi', false)
        );
        $this->assertSame('mailto:hello@example.com', FrontendRouteUrl::localized('mailto:hello@example.com'));
    }
}
