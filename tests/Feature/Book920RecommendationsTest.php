<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\ContentTranslation;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Book920RecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_book_with_no_category_peers_has_two_distinct_shelves(): void
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('BOOK920')->generate('book920-bookle');
        $book = CatalogProduct::where('slug', 'book920-thau-hieu-chinh-minh')->firstOrFail();
        $response = $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $book->slug]))
            ->assertOk()->assertSee('id="book20-related-title"', false)->assertSee('id="book20-new-title"', false);
        $related = collect($response->viewData('relatedProducts'));
        $latest = collect($response->viewData('latestProducts'));
        $this->assertCount(4, $related);
        $this->assertCount(4, $latest);
        $this->assertCount(8, $related->concat($latest)->unique('url'));
        foreach ($related->concat($latest) as $item) {
            $this->assertNotSame($book->name, $item['title']);
            $this->get($item['url'])->assertOk();
        }
        if ($path = getenv('BOOK920_RECOMMENDATIONS_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_recommendations_prioritize_category_and_newest_public_books_within_website(): void
    {
        SiteProfile::create(['site_name' => 'Books', 'website_type' => 'ecommerce', 'active_theme_key' => 'BOOK920']);
        $category = CatalogCategory::create(['name' => 'Tâm lý', 'slug' => 'tam-ly', 'is_active' => true]);
        $current = $this->book('current', ['catalog_category_id' => $category->id]);
        $peer = $this->book('same-category', ['catalog_category_id' => $category->id, 'created_at' => now()->subYears(2)]);
        for ($index = 0; $index < 9; $index++) {
            $this->book('book-'.$index, ['created_at' => now()->subDays($index + 1)]);
        }
        $this->book('inactive', ['is_active' => false]);
        $this->book('another-website', ['website_key' => 'other-site']);
        $draft = $this->book('unpublished');
        ContentTranslation::where('resource_type', 'catalog_product')->where('resource_id', (string) $draft->id)
            ->update(['translation_status' => 'draft']);
        $response = $this->get(route('site.catalog.product', ['locale' => 'vi', 'slug' => $current->slug]))->assertOk();
        $related = collect($response->viewData('relatedProducts'));
        $latest = collect($response->viewData('latestProducts'));
        $this->assertSame($peer->name, $related->first()['title']);
        $this->assertSame(['book-3', 'book-4', 'book-5', 'book-6'], $latest->pluck('title')->all());
        foreach (['current', 'inactive', 'another-website', 'unpublished'] as $excluded) {
            $this->assertNotContains($excluded, $related->concat($latest)->pluck('title'));
        }
    }

    public function test_small_catalog_does_not_duplicate_or_invent_recommendations(): void
    {
        SiteProfile::create(['site_name' => 'Books', 'website_type' => 'ecommerce', 'active_theme_key' => 'BOOK920']);
        $current = $this->book('only-book');
        $url = route('site.catalog.product', ['locale' => 'vi', 'slug' => $current->slug]);
        $this->get($url)->assertOk()->assertDontSee('id="book20-related-title"', false)->assertDontSee('id="book20-new-title"', false);
        $this->book('second-book');
        $this->book('third-book');
        $response = $this->get($url)->assertOk();
        $this->assertCount(1, $response->viewData('relatedProducts'));
        $this->assertCount(1, $response->viewData('latestProducts'));
        $this->assertNotSame($response->viewData('relatedProducts')[0]['url'], $response->viewData('latestProducts')[0]['url']);
    }

    private function book(string $name, array $attributes = []): CatalogProduct
    {
        $book = new CatalogProduct;
        $book->forceFill(array_merge(['name' => $name, 'slug' => $name, 'sku' => $name, 'price' => 100000, 'stock' => 5, 'is_active' => true], $attributes))->save();

        return $book;
    }
}
