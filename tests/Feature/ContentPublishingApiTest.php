<?php

namespace Tests\Feature;

use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\ContentApiToken;
use App\Models\ContentTranslation;
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
            'abilities' => [
                'posts.read',
                'posts.write',
                'posts.publish',
                'media.write',
                'translations.read',
                'translations.write',
                'translations.publish',
            ],
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

    public function test_api_rejects_cover_larger_than_one_megabyte(): void
    {
        Storage::fake('public');

        $this->withHeader('Accept', 'application/json')
            ->withToken($this->rawToken)
            ->post('/api/v1/cms/media', [
                'external_id' => 'article:oversized-featured',
                'file' => UploadedFile::fake()->image('large-cover.jpg', 1440, 810)->size(1025),
            ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, CmsMedia::query()->count());
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
        ContentApiToken::query()->firstOrFail()->update([
            'abilities' => ['posts.read', 'posts.write', 'media.write'],
        ]);
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

    public function test_api_upserts_and_auto_publishes_an_english_machine_translation(): void
    {
        $category = CmsCategory::query()->create(['name' => 'Hướng dẫn', 'slug' => 'huong-dan']);
        $externalId = 'tech-content:core-web-vitals';

        $postResponse = $this->withToken($this->rawToken)->postJson('/api/v1/cms/posts/upsert', [
            'external_id' => $externalId,
            'category_id' => $category->id,
            'title' => 'Hướng dẫn Core Web Vitals',
            'slug' => 'huong-dan-core-web-vitals',
            'body' => '<p>Nội dung tiếng Việt.</p>',
            'status' => 'published',
        ])->assertCreated();

        $postId = $postResponse->json('data.id');
        $encodedExternalId = rawurlencode($externalId);
        $translationUrl = "/api/v1/cms/posts/{$encodedExternalId}/translations/en/upsert";
        $translationResponse = $this->withToken($this->rawToken)->postJson($translationUrl, [
            'title' => 'How to improve Core Web Vitals',
            'slug' => 'improve-core-web-vitals',
            'excerpt' => 'A practical performance guide.',
            'body' => '<p>English content.</p>',
            'meta_title' => 'Improve Core Web Vitals: A Practical Guide',
            'meta_description' => 'Measure and improve Core Web Vitals for a faster website.',
            'meta_keywords' => 'Core Web Vitals, web performance, LCP, INP, CLS',
        ]);

        $translationResponse->assertOk()
            ->assertJsonPath('data.translation_status', 'published')
            ->assertJsonPath('data.is_machine_translated', true)
            ->assertJsonPath('data.payload.title', 'How to improve Core Web Vitals')
            ->assertJsonPath('data.canonical_path', '/n/improve-core-web-vitals')
            ->assertJsonPath('data.public_path', '/en/n/improve-core-web-vitals');

        $this->assertDatabaseHas('content_translations', [
            'resource_type' => 'cms_post',
            'resource_id' => (string) $postId,
            'locale' => 'en',
            'translation_status' => 'published',
            'is_machine_translated' => true,
        ]);
        $this->withToken($this->rawToken)
            ->getJson("/api/v1/cms/posts/{$encodedExternalId}/translations/en")
            ->assertOk()
            ->assertJsonPath('data.payload.slug', 'improve-core-web-vitals');
        $this->get('/en/n/improve-core-web-vitals')
            ->assertOk()
            ->assertSee('English content', false);

        $this->withToken($this->rawToken)->postJson($translationUrl, [
            'title' => 'A better Core Web Vitals guide',
            'slug' => 'better-core-web-vitals',
            'body' => '<p>Updated English content.</p>',
        ])->assertOk()
            ->assertJsonPath('data.public_path', '/en/n/better-core-web-vitals');

        $this->assertSame(2, ContentTranslation::query()
            ->where('resource_type', 'cms_post')
            ->where('resource_id', (string) $postId)
            ->count());
        $this->get('/en/n/improve-core-web-vitals')
            ->assertRedirect('/en/n/better-core-web-vitals');
    }

    public function test_translation_api_enforces_source_locale_and_publish_ability(): void
    {
        $category = CmsCategory::query()->create(['name' => 'Lập trình', 'slug' => 'lap-trinh']);
        $externalId = 'tech-content:translation-guard';
        $this->withToken($this->rawToken)->postJson('/api/v1/cms/posts/upsert', [
            'external_id' => $externalId,
            'category_id' => $category->id,
            'title' => 'Bài nguồn',
            'body' => '<p>Nội dung nguồn.</p>',
            'status' => 'published',
        ])->assertCreated();

        $payload = [
            'title' => 'English title',
            'body' => '<p>English body.</p>',
        ];
        $this->withToken($this->rawToken)
            ->postJson("/api/v1/cms/posts/{$externalId}/translations/vi/upsert", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        ContentApiToken::query()->firstOrFail()->update([
            'abilities' => ['posts.read', 'posts.write', 'translations.read', 'translations.write'],
        ]);
        $this->withToken($this->rawToken)
            ->postJson("/api/v1/cms/posts/{$externalId}/translations/en/upsert", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publish');

        $this->withToken($this->rawToken)
            ->postJson("/api/v1/cms/posts/{$externalId}/translations/en/upsert", [
                ...$payload,
                'publish' => false,
            ])->assertOk()
            ->assertJsonPath('data.translation_status', 'machine_draft')
            ->assertJsonPath('data.public_path', null);
    }
}
