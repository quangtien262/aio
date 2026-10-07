<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\Site;
use Database\Seeders\Nt501ArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Nt501ServiceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_listing_counts_actual_cards_instead_of_paginator_fields(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('NT501', $generator->presetsForTheme('NT501')[0]['key']);
        $response = $this->get('/vi/s')->assertOk()->assertDontSee('Tìm hiểu ngay');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $cards = $xpath->query('//article[@class="xd-service-card"]');
        $this->assertSame(6, $cards->length);
        $this->assertSame('06', trim($xpath->query('//div[@class="xd-cms-stats"]/strong')->item(0)->textContent));
        foreach ($cards as $card) {
            $this->assertSame(2, $xpath->query('.//a', $card)->length);
            $this->assertNotEmpty($xpath->query('.//h2/a', $card)->item(0)->getAttribute('href'));
        }
        if ($path = getenv('NT501_LIST_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }

    public function test_service_detail_displays_content_and_ten_latest_public_posts_from_its_website(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('NT501', $generator->presetsForTheme('NT501')[0]['key']);
        Site::create(['domain' => 'another.demo.test', 'website_key' => 'another', 'theme_key' => 'NT501', 'status' => 'active']);
        $this->seed(Nt501ArticleSeeder::class);
        $count = CmsPost::withoutGlobalScope('current_website')->count();
        $this->seed(Nt501ArticleSeeder::class);
        $this->assertSame($count, CmsPost::withoutGlobalScope('current_website')->count());
        $this->assertSame(10, CmsPost::query()->forWebsite('another')->count());

        foreach ([['Foreign post', 'published', now(), 'other'], ['Draft post', 'draft', now(), 'website-main'], ['Scheduled post', 'published', now()->addDay(), 'website-main']] as [$title, $status, $date, $website]) {
            CmsPost::create(['website_key' => $website, 'title' => $title, 'slug' => str($title)->slug(), 'status' => $status, 'publish_at' => $date, 'body' => '<p>Hidden</p>']);
        }
        $latest = CmsPost::query()->where('status', 'published')->where('publish_at', '<=', now())->latest('publish_at')->orderByDesc('id')->take(10)->get();
        $service = CmsService::where('slug', 'nt501-thiet-ke-noi-that')->firstOrFail();
        $response = $this->get('/vi/ser/'.$service->slug)->assertOk()
            ->assertSee($service->summary)->assertSee($service->content, false)
            ->assertSee('Bài viết mới nhất')->assertDontSee('Foreign post')->assertDontSee('Draft post')->assertDontSee('Scheduled post');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $cards = $xpath->query('//article[@class="nt-service-post"]');
        $this->assertSame(10, $cards->length);
        foreach ($latest as $index => $post) {
            $this->assertStringContainsString($post->title, $cards->item($index)->textContent);
        }
        if ($path = getenv('NT501_SERVICE_PREVIEW')) {
            file_put_contents($path, $response->getContent());
        }
    }
}
