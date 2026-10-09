<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Xd0305AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_limits_each_group_and_filters_unpublished_and_other_websites(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0305', 'xd0305-business-consulting');
        $entry = CmsPage::where('slug', 'xd0305-gioi-thieu')->firstOrFail();
        foreach ([CatalogProduct::class, CmsService::class, CmsPost::class] as $model) {
            $original = $model::firstOrFail();
            for ($i = 0; $i < 12; $i++) {
                $copy = $original->replicate();
                $copy->slug = 'sidebar-'.class_basename($model).'-'.$i;
                if ($copy instanceof CatalogProduct) {
                    $copy->sku = 'SIDEBAR-'.$i;
                }
                $copy->save();
            }
            $foreign = $original->replicate();
            $foreign->slug = 'foreign-'.class_basename($model);
            $foreign->website_key = 'other-website';
            $foreign->save();
            $hidden = $original->replicate();
            $hidden->slug = 'hidden-'.class_basename($model);
            if ($model === CatalogProduct::class) {
                $hidden->is_active = false;
                $hidden->sku = 'HIDDEN-SIDEBAR';
            } else {
                $hidden->publish_at = now()->addDay();
            }
            $hidden->save();
        }
        $response = $this->get('/vi/p/'.$entry->slug)->assertOk()->assertDontSee('Liên kết nhanh');
        foreach (['articleLatestProducts', 'articleServices', 'serviceSidebarPosts'] as $key) {
            $response->assertViewHas($key, fn ($items) => $items->count() === 10
                && $items->every(fn ($item) => ! str_contains($item['url'], 'hidden-') && ! str_contains($item['url'], 'foreign-')));
        }
        $response->assertViewHas('articleServices', fn ($items) => $items->every(fn ($item) => ! str_ends_with($item['url'], '/'.$entry->slug)));
    }

    public function test_empty_groups_are_hidden_and_article_uses_full_width(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0305', 'xd0305-business-consulting');
        $entry = CmsPage::where('slug', 'xd0305-gioi-thieu')->firstOrFail();
        CatalogProduct::query()->update(['is_active' => false]);
        $this->get('/vi/p/'.$entry->slug)->assertOk()
            ->assertDontSee('data-content-collection="products"', false)
            ->assertSee('data-content-collection="services"', false)
            ->assertSee('data-content-collection="news"', false);
        CmsPost::query()->update(['status' => 'draft']);
        $this->get('/vi/p/'.$entry->slug)->assertOk()
            ->assertDontSee('data-content-collection="news"', false)
            ->assertSee('data-content-collection="services"', false);
        CmsService::query()->update(['status' => 'draft']);
        $this->get('/vi/p/'.$entry->slug)->assertOk()
            ->assertDontSee('data-content-collection=', false)
            ->assertSee('xd305-content-page', false);
    }
}
