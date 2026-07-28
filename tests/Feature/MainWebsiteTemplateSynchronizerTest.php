<?php

namespace Tests\Feature;

use App\Support\MainWebsiteTemplateSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MainWebsiteTemplateSynchronizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.ht', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('ht');

        Schema::connection('ht')->create('website_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug', 160);
            $table->string('theme_code', 20)->default('TH01');
            $table->decimal('base_price', 15, 2)->default(0);
            $table->string('currency', 10)->default('VND');
            $table->string('preview_theme', 40)->default('violet');
            $table->text('demo_url')->nullable();
            $table->unsignedInteger('current_version_number')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('ht')->create('website_template_media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->string('media_type')->default('gallery');
            $table->string('file_path');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_only_supports_the_ht_vietnam_demo_root_domain(): void
    {
        $synchronizer = app(MainWebsiteTemplateSynchronizer::class);

        $this->assertTrue($synchronizer->supports('https://demo.htvietnam.vn/'));
        $this->assertFalse($synchronizer->supports('demo.example.com'));
        $this->assertSame([
            'inserted' => 0,
            'updated' => 0,
            'items' => [],
        ], $synchronizer->syncThemes([], 'demo.example.com'));
    }

    public function test_it_inserts_and_updates_templates_by_theme_code_without_overwriting_category(): void
    {
        $synchronizer = app(MainWebsiteTemplateSynchronizer::class);
        $theme = [
            'key' => 'EC910',
            'version' => '1.0.0',
            'preview' => ['thumbnail' => 'thumbnail-ec910.png'],
            'preview_urls' => [
                'thumbnail' => 'https://aio.test/theme-previews/EC910/thumbnail-ec910.png',
            ],
        ];

        $insertResult = $synchronizer->syncThemes([$theme], 'demo.htvietnam.vn');

        $this->assertSame(1, $insertResult['inserted']);
        $this->assertSame(0, $insertResult['updated']);
        $this->assertDatabaseHas('website_templates', [
            'category_id' => null,
            'name' => 'EC910',
            'slug' => 'EC910',
            'theme_code' => 'EC910',
            'base_price' => 199000,
            'preview_theme' => 'thumbnail-ec910.png',
            'demo_url' => 'https://ec910.demo.htvietnam.vn',
            'current_version_number' => 1,
        ], 'ht');
        $templateId = (int) DB::connection('ht')
            ->table('website_templates')
            ->where('theme_code', 'EC910')
            ->value('id');
        $this->assertDatabaseHas('website_template_media', [
            'template_id' => $templateId,
            'media_type' => 'thumbnail',
            'file_path' => 'https://aio.test/theme-previews/EC910/thumbnail-ec910.png',
            'alt_text' => 'EC910 thumbnail',
            'sort_order' => 0,
            'is_primary' => 1,
        ], 'ht');

        DB::connection('ht')->table('website_templates')
            ->where('theme_code', 'EC910')
            ->update([
                'category_id' => 7,
                'name' => 'Tên cũ',
                'deleted_at' => now(),
            ]);

        $updateResult = $synchronizer->syncThemes([[
            ...$theme,
            'version' => '0.2.0',
            'preview' => ['thumbnail' => 'ec910-new.png'],
            'preview_urls' => [
                'thumbnail' => 'https://aio.test/theme-previews/EC910/ec910-new.png',
            ],
        ]], 'demo.htvietnam.vn');

        $this->assertSame(0, $updateResult['inserted']);
        $this->assertSame(1, $updateResult['updated']);
        $this->assertSame(1, DB::connection('ht')->table('website_templates')->count());
        $this->assertDatabaseHas('website_templates', [
            'category_id' => 7,
            'name' => 'EC910',
            'slug' => 'EC910',
            'theme_code' => 'EC910',
            'preview_theme' => 'ec910-new.png',
            'current_version_number' => 2,
            'deleted_at' => null,
        ], 'ht');
        $this->assertSame(1, DB::connection('ht')->table('website_template_media')->count());
        $this->assertDatabaseHas('website_template_media', [
            'template_id' => $templateId,
            'media_type' => 'thumbnail',
            'file_path' => 'https://aio.test/theme-previews/EC910/ec910-new.png',
            'alt_text' => 'EC910 thumbnail',
            'sort_order' => 0,
            'is_primary' => 1,
        ], 'ht');
    }
}
