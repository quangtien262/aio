<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\ContentApiToken;
use App\Models\ModuleInstallation;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentPublishingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $rawToken = 'ctk_test_content_api_token';

    protected function setUp(): void
    {
        parent::setUp();

        Site::query()->updateOrCreate(['website_key' => 'website-main'], [
            'name' => 'Tech HT Việt Nam',
            'theme_key' => 'NEWS88',
            'status' => 'active',
        ]);
        ModuleInstallation::query()->updateOrCreate(['key' => 'cms'], [
            'name' => 'CMS',
            'version' => '0.2.6',
            'status' => 'enabled',
            'website_types' => [],
            'dependencies' => [],
        ]);
        ContentApiToken::query()->create([
            'name' => 'Content tests',
            'source_key' => 'tech-content',
            'website_key' => 'website-main',
            'token_hash' => hash('sha256', $this->rawToken),
            'abilities' => ['posts.read', 'posts.write', 'media.write'],
        ]);
    }

    public function test_api_requires_a_valid_bearer_token(): void
    {
        $this->getJson('/api/v1/cms/categories')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/v1/cms/categories')->assertUnauthorized();
    }

    public function test_api_lists_only_categories_from_the_token_website(): void
    {
        CmsCategory::query()->create(['name' => 'Hướng dẫn', 'slug' => 'huong-dan']);

        $response = $this->withToken($this->rawToken)->getJson('/api/v1/cms/categories');

        $response->assertOk()->assertJsonPath('data.0.slug', 'huong-dan');
    }

    public function test_api_uploads_media_idempotently(): void
    {
        Storage::fake('public');
        config()->set('filesystems.disks.public.url', 'http://stale.example/files');
        $hash = hash('sha256', 'same-cover');
        $payload = [
            'external_id' => 'article:featured',
            'payload_hash' => $hash,
            'title' => 'Ảnh đại diện',
            'file' => UploadedFile::fake()->image('cover.jpg', 1440, 810),
        ];

        $first = $this->withToken($this->rawToken)->post('/api/v1/cms/media', $payload);
        $first->assertCreated()
            ->assertJsonPath(
                'data.file_url',
                fn (string $url): bool => ! str_contains($url, 'stale.example')
                    && str_contains($url, '/files/cms/website-main/'),
            );
        $mediaId = $first->json('data.id');

        $second = $this->withToken($this->rawToken)->post('/api/v1/cms/media', [
            ...$payload,
            'file' => UploadedFile::fake()->image('cover.jpg', 1440, 810),
        ]);
        $second->assertOk()->assertJsonPath('data.id', $mediaId);
        $this->assertSame(1, CmsMedia::query()->count());
    }

    public function test_api_creates_then_updates_post_without_losing_seo_or_tags(): void
    {
        $category = CmsCategory::query()->create(['name' => 'Lập trình', 'slug' => 'lap-trinh']);
        $payload = [
            'external_id' => 'article:laravel-ci',
            'payload_hash' => hash('sha256', 'v1'),
            'category_id' => $category->id,
            'title' => 'Thiết lập CI cho Laravel',
            'slug' => 'ci-laravel-github-actions',
            'excerpt' => 'Hướng dẫn thiết lập CI.',
            'body' => '<p>Nội dung bài viết.</p>',
            'meta_title' => 'CI Laravel bằng GitHub Actions: Hướng dẫn đầy đủ',
            'meta_keywords' => 'Laravel, GitHub Actions, CI',
            'meta_description' => 'Thiết lập kiểm thử Laravel tự động trên GitHub Actions.',
            'tags' => ['Laravel', 'GitHub Actions', 'CI/CD'],
        ];

        $first = $this->withToken($this->rawToken)->postJson('/api/v1/cms/posts/upsert', $payload);
        $first->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'ci-laravel-github-actions')
            ->assertJsonPath('data.meta_title', $payload['meta_title']);
        $postId = $first->json('data.id');

        $second = $this->withToken($this->rawToken)->postJson('/api/v1/cms/posts/upsert', [
            ...$payload,
            'payload_hash' => hash('sha256', 'v2'),
            'title' => 'Thiết lập CI Laravel thực tế',
        ]);
        $second->assertOk()
            ->assertJsonPath('data.id', $postId)
            ->assertJsonCount(3, 'data.tags');
        $this->assertDatabaseHas('cms_posts', [
            'id' => $postId,
            'website_key' => 'website-main',
            'slug' => 'ci-laravel-github-actions',
            'meta_title' => $payload['meta_title'],
        ]);
    }

    public function test_api_rejects_cross_website_category_and_direct_publish_without_ability(): void
    {
        $category = CmsCategory::withoutGlobalScopes()->create([
            'website_key' => 'another-site',
            'name' => 'Danh mục khác',
            'slug' => 'danh-muc-khac',
        ]);
        $payload = [
            'external_id' => 'article:blocked',
            'category_id' => $category->id,
            'title' => 'Không được tạo',
            'status' => 'draft',
        ];

        $this->withToken($this->rawToken)
            ->postJson('/api/v1/cms/posts/upsert', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $validCategory = CmsCategory::query()->create(['name' => 'Giải pháp', 'slug' => 'giai-phap']);
        $this->withToken($this->rawToken)
            ->postJson('/api/v1/cms/posts/upsert', [
                ...$payload,
                'category_id' => $validCategory->id,
                'status' => 'published',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
