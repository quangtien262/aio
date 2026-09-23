<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\CatalogProduct;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Book920SubpagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedBooks(): CatalogProduct
    {
        app(ThemeDemoContentProviderRegistry::class)->forTheme('BOOK920')->generate('book920-bookle');

        return CatalogProduct::where('slug', 'book920-thau-hieu-chinh-minh')->firstOrFail();
    }

    private function preview(string $name, string $html): void
    {
        if ($directory = getenv('BOOK920_PREVIEW_DIR')) {
            file_put_contents($directory.'/'.$name.'.html', $html);
        }
    }

    public function test_all_content_and_catalog_pages_render_actual_records(): void
    {
        $product = $this->seedBooks();
        $product->images()->create(['image_url' => '/theme-demo/book920/book-2.webp', 'sort_order' => 20]);
        $post = CmsPost::firstOrFail();
        $page = CmsPage::create(['title' => 'Trang giới thiệu riêng', 'slug' => 'gioi-thieu-rieng', 'status' => 'published', 'body' => '<p>Nội dung trang riêng.</p>', 'publish_at' => now()]);
        $service = CmsService::create(['title' => 'Tư vấn tủ sách', 'slug' => 'tu-van-tu-sach', 'status' => 'published', 'summary' => 'Tủ sách theo sở thích.', 'content' => '<p>Dịch vụ chọn sách.</p>', 'publish_at' => now()]);
        CmsService::create(['title' => 'Hỗ trợ độc giả', 'slug' => 'ho-tro-doc-gia', 'status' => 'published', 'publish_at' => now()]);
        $project = CmsProject::create(['title' => 'Ngày hội đọc sách', 'slug' => 'ngay-hoi', 'status' => 'published', 'content' => '<p>Cùng chia sẻ niềm vui đọc.</p>', 'publish_at' => now()]);

        $routes = [
            ['product', 'site.catalog.product', ['slug' => $product->slug], $product->name, '178.000đ'],
            ['category', 'site.catalog.category', ['slug' => $product->category->slug], $product->name, 'book20-filter'],
            ['search', 'site.catalog.search', ['q' => $product->name, 'sort' => 'price_asc'], $product->name, 'value="price_asc" selected'],
            ['news', 'site.blog.index', [], $post->title, '/vi/n/'.$post->slug],
            ['article', 'site.blog.show', ['slug' => $post->slug], $post->title, strip_tags($post->excerpt)],
            ['cms', 'site.pages.show', ['slug' => $page->slug], $page->title, 'Nội dung trang riêng.'],
            ['services', 'site.services.index', [], $service->title, '/vi/ser/'.$service->slug],
            ['service', 'site.services.show', ['slug' => $service->slug], $service->title, 'Dịch vụ chọn sách.'],
            ['projects', 'site.projects.index', [], $project->title, '/vi/prj/'.$project->slug],
            ['project', 'site.projects.show', ['slug' => $project->slug], $project->title, 'Cùng chia sẻ niềm vui đọc.'],
            ['contact', 'site.contact', [], 'Nội dung cần hỗ trợ', 'name="message"'],
            ['empty-cart', 'site.cart.index', [], 'Giỏ hàng của bạn đang trống', 'Khám phá tủ sách'],
            ['home', 'site.home', [], 'Sách nổi bật', 'book20-header'],
        ];
        foreach ($routes as [$name, $route, $parameters, $title, $content]) {
            $response = $this->get(route($route, ['locale' => 'vi', ...$parameters]))
                ->assertOk()->assertSee($title)->assertSee($content, false)
                ->assertSee('class="book20-header"', false)->assertSee('class="book20-footer"', false);
            $this->preview($name, $response->getContent());
        }
        $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'no-match-xyz']))
            ->assertOk()->assertSee('Chưa tìm thấy sách phù hợp');
    }

    public function test_cart_update_remove_checkout_and_confirmation_work(): void
    {
        Mail::fake();
        $product = $this->seedBooks();
        $this->post(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), ['quantity' => 2])->assertRedirect();
        $response = $this->get(route('site.cart.index', ['locale' => 'vi']))
            ->assertOk()->assertSee($product->name)->assertSee('356.000đ')->assertSee('<em>2</em>', false);
        $this->preview('cart', $response->getContent());
        $this->post(route('site.cart.update', ['locale' => 'vi', 'productId' => $product->id]), ['quantity' => 3])->assertRedirect();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertSee('534.000đ');
        $this->get(route('site.checkout.index', ['locale' => 'vi']))->assertRedirect(route('site.cart.index', ['locale' => 'vi']));
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');
        $response = $this->get(route('site.checkout.index', ['locale' => 'vi']))
            ->assertOk()->assertSee('name="delivery_address"', false)->assertSee($product->name)->assertSee('534.000đ');
        $this->preview('checkout', $response->getContent());
        $this->post(route('site.checkout.store', ['locale' => 'vi']), [])->assertSessionHasErrors(['customer_name', 'delivery_address']);
        $this->post(route('site.checkout.store', ['locale' => 'vi']), [
            'customer_name' => 'Độc giả thử nghiệm', 'customer_phone' => '0900000000', 'customer_email' => 'reader@example.test',
            'delivery_address' => '12 Đường Sách', 'payment_method' => 'cod',
        ])->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame(3, $order->item_count);
        $this->assertSame(534000.0, (float) $order->subtotal);
        $response = $this->get(route('site.checkout.success', ['locale' => 'vi', 'order' => $order->id]))
            ->assertOk()->assertSee($order->order_code)->assertSee('534.000đ');
        $this->preview('success', $response->getContent());
        $this->post(route('site.cart.add', ['locale' => 'vi', 'slug' => $product->slug]), ['quantity' => 1])->assertRedirect();
        $this->post(route('site.cart.remove', ['locale' => 'vi', 'productId' => $product->id]))->assertRedirect();
        $this->get(route('site.cart.index', ['locale' => 'vi']))->assertSee('Giỏ hàng của bạn đang trống');
    }

    public function test_contact_form_preserves_invalid_input_and_displays_success(): void
    {
        Mail::fake();
        $this->seedBooks();
        $url = route('site.contact', ['locale' => 'vi']);
        $this->from($url)->post(route('site.contact.submit', ['locale' => 'vi']), ['name' => 'Độc giả', 'email' => 'invalid', 'message' => 'short'])
            ->assertRedirect($url);
        $this->get($url)->assertSee('value="Độc giả"', false)->assertSee('role="alert"', false);
        $this->from($url)->post(route('site.contact.submit', ['locale' => 'vi']), [
            'name' => 'Độc giả', 'email' => 'reader@example.test', 'message' => 'Tôi cần tư vấn tìm sách phù hợp.', 'source' => 'contact',
        ])->assertRedirect($url)->assertSessionHas('contact_status');
        $this->get($url)->assertSee('Đã gửi yêu cầu liên hệ.');
        $this->from($url)->post(route('site.newsletter.subscribe', ['locale' => 'vi']), ['email' => 'newsletter@example.test', 'source' => 'book920-footer'])->assertRedirect($url);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'newsletter@example.test', 'source' => 'book920-footer']);
        $this->get($url)->assertSee('Đã đăng ký nhận bản tin thành công.');
    }

    public function test_search_pagination_preserves_query_and_sort(): void
    {
        $this->seedBooks();
        for ($index = 1; $index <= 25; $index++) {
            CatalogProduct::create(['name' => 'PaginationBook '.$index, 'slug' => 'pagination-book-'.$index, 'sku' => 'PAGE-'.$index, 'price' => $index * 10000, 'stock' => 5, 'is_active' => true]);
        }
        $response = $this->get(route('site.catalog.search', ['locale' => 'vi', 'q' => 'PaginationBook', 'sort' => 'price_asc']))
            ->assertOk()->assertSee('rel="next"', false)->assertSee('PaginationBook 1');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $next = (new \DOMXPath($document))->query('//a[@rel="next"]')->item(0)->getAttribute('href');
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame('PaginationBook', $query['q']);
        $this->assertSame('price_asc', $query['sort']);
        $this->get($next)->assertOk()->assertSee('PaginationBook 25')->assertSee('rel="prev"', false);
    }
}
