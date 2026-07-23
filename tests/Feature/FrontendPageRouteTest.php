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
}
