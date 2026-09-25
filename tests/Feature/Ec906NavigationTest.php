<?php

namespace Tests\Feature;

use App\Models\CmsMenu;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ec906NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_navigation_preserves_nested_links_on_home_and_search(): void
    {
        SiteProfile::create(['site_name' => 'Mini Mart', 'website_type' => 'ecommerce', 'active_theme_key' => 'EC906']);
        CmsMenu::create(['name' => 'Main', 'location' => 'primary-navigation', 'items' => [
            ['label' => 'Trang chủ', 'url' => '/vi'],
            ['label' => 'Sản phẩm', 'url' => '/vi/tim-kiem', 'children' => [
                ['label' => 'Chăm sóc gia đình', 'url' => '/vi/tim-kiem?category=family', 'children' => [
                    ['label' => 'Giặt giũ', 'url' => '/vi/tim-kiem?q=giat', 'children' => [['label' => 'Nước giặt', 'url' => '/vi/tim-kiem?q=nuoc-giat', 'target' => '_blank']]],
                ]],
                ['label' => 'Đồ dùng bếp', 'url' => '/vi/tim-kiem?category=kitchen'],
            ]],
            ['label' => 'Tin tức', 'url' => '/vi/c'],
        ]]);
        foreach (['home' => '/vi', 'search' => '/vi/tim-kiem'] as $name => $url) {
            $response = $this->get($url)->assertOk()->assertSee('ec96-desktop-nav-1-0-0', false)->assertSee('ec96-mobile-nav-1-0-0', false)->assertSee('Nước giặt')->assertSee('noopener noreferrer');
            if ($folder = getenv('EC906_NAV_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }file_put_contents($folder.'/'.$name.'.html', $response->getContent());
            }
        }
    }
}
