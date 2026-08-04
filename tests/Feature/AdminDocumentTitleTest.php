<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_document_title_uses_the_current_site_name_from_database(): void
    {
        SiteProfile::query()->create([
            'website_key' => 'website-main',
            'site_name' => 'Thương hiệu Khách hàng',
            'website_type' => 'ecommerce',
            'active_theme_key' => 'NT504',
            'branding' => [
                'company_name' => 'Tên công ty trong branding',
            ],
        ]);

        $admin = Admin::factory()->create(['status' => 'active']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('<title>Thương hiệu Khách hàng Admin</title>', false)
            ->assertDontSee('HTVietNam Admin');
    }
}
