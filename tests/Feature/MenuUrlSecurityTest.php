<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuUrlSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_store_executable_menu_url_scheme(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = Admin::query()->findOrFail(Admin::SYSTEM_OWNER_ID);

        $this->actingAs($owner, 'admin')
            ->postJson('/admin/api/cms/menus', [
                'name' => 'Unsafe menu',
                'location' => 'primary',
                'items' => [[
                    'label' => 'Run script',
                    'url' => 'javascript:alert(document.domain)',
                    'link_type' => 'custom',
                    'custom_url' => 'javascript:alert(document.domain)',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.url', 'items.0.custom_url']);
    }
}
