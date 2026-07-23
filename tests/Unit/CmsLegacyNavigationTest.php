<?php

namespace Tests\Unit;

use Tests\TestCase;

class CmsLegacyNavigationTest extends TestCase
{
    public function test_legacy_landing_configuration_screens_are_hidden_but_retained(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('modules/Cms/module.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $menus = collect($manifest['menus'] ?? [])->keyBy('key');

        foreach (['cms-featured-categories', 'cms-side-promos'] as $menuKey) {
            $this->assertTrue((bool) data_get($menus->get($menuKey), 'hidden'));
            $this->assertNotEmpty(data_get($menus->get($menuKey), 'route'));
        }

        $this->assertTrue(app('router')->has('admin.api.cms.featured-categories.index'));
        $this->assertTrue(app('router')->has('admin.api.cms.side-promos.index'));
    }
}
