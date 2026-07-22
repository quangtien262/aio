<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Foot401ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_foot401_storefront_admin_mode_renders_landing_block_editor(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('FOOT401', 'interior-home');
        $this->actingAs(Admin::factory()->create(), 'admin');

        $response = $this->get(route('site.home', ['locale' => 'vi', 'mod' => 'admin']));

        $response
            ->assertOk()
            ->assertSee('data-xd-editor', false)
            ->assertSee('data-xd-edit-block', false)
            ->assertSee('Sửa khối');

        $this->assertSame(6, substr_count($response->getContent(), 'data-xd-edit-block='));
        $this->assertSame(6, substr_count($response->getContent(), 'data-landing-block-id='));

        $this->get(route('site.home', ['locale' => 'vi']))
            ->assertOk()
            ->assertDontSee('data-xd-editor', false)
            ->assertDontSee('data-xd-edit-block', false);
    }
}
