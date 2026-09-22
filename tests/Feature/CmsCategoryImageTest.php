<?php

namespace Tests\Feature;

use App\Models\{Admin, CmsCategory, SiteProfile};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsCategoryImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_image_can_be_created_updated_shown_and_cleared(): void
    {
        SiteProfile::create(['website_key' => 'website-main', 'site_name' => 'News', 'website_type' => 'news', 'active_theme_key' => 'NEWS88']);
        $this->actingAs(Admin::factory()->create(['id' => 1, 'is_system_owner' => true]), 'admin');
        $payload = ['name' => 'Technology', 'slug' => 'technology', 'image_url' => '/theme-demo/news88/hero-mekong.png'];
        $id = $this->postJson('/admin/api/cms/categories', $payload)->assertCreated()->assertJsonPath('data.image_url', $payload['image_url'])->json('data.id');
        $this->getJson('/admin/api/cms/categories')->assertOk()->assertJsonPath('data.items.0.image_url', $payload['image_url']);
        $this->get('/vi/news-categories')->assertOk()->assertSee('src="'.$payload['image_url'].'"', false);
        $this->putJson('/admin/api/cms/categories/'.$id, [...$payload, 'image_url' => 'https://example.com/category.jpg'])->assertOk()->assertJsonPath('data.image_url', 'https://example.com/category.jpg');
        foreach (['javascript:alert(1)', '//untrusted.test/image.png'] as $invalid) {
            $this->putJson('/admin/api/cms/categories/'.$id, [...$payload, 'image_url' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('image_url');
        }
        $this->putJson('/admin/api/cms/categories/'.$id, [...$payload, 'image_url' => null])->assertOk()->assertJsonPath('data.image_url', null);
        $this->assertNull(CmsCategory::findOrFail($id)->image_url);
    }
}
