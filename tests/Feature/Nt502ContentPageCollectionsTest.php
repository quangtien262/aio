<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\CmsPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt502ContentPageCollectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_limits_each_group_and_filters_unpublished_and_other_websites(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('NT502', 'nt502-dola-furniture');
        $entry = CmsPage::where('slug', 'gioi-thieu')->firstOrFail();
        foreach ([CatalogProduct::class, CmsPost::class] as $model) {
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
        foreach (['articleLatestProducts', 'serviceSidebarPosts'] as $key) {
            $response->assertViewHas($key, fn ($items) => $items->count() === 10
                && $items->every(fn ($item) => ! str_contains($item['url'], 'hidden-') && ! str_contains($item['url'], 'foreign-')));
        }
        $response->assertSee('data-page-collection="products"', false)->assertSee('data-page-collection="news"', false);
    }

    public function test_empty_groups_are_hidden_independently(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('NT502', 'nt502-dola-furniture');
        CatalogProduct::query()->update(['is_active' => false]);
        $this->get('/vi/p/gioi-thieu')->assertOk()
            ->assertDontSee('data-page-collection="products"', false)
            ->assertSee('data-page-collection="news"', false);
        CmsPost::query()->update(['status' => 'draft']);
        $this->get('/vi/p/gioi-thieu')->assertOk()
            ->assertDontSee('data-page-collection="products"', false)
            ->assertDontSee('data-page-collection="news"', false);
    }
}
