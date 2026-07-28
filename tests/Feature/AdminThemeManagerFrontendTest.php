<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminThemeManagerFrontendTest extends TestCase
{
    public function test_theme_manager_supports_name_search_and_query_driven_pagination(): void
    {
        $source = file_get_contents(
            resource_path('admin/src/modules/themes/pages/ThemeManagerPage.jsx')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString("searchParams.get('q')", $source);
        $this->assertStringContainsString("searchParams.get('page')", $source);
        $this->assertStringContainsString("searchParams.get('per_page')", $source);
        $this->assertStringContainsString("String(theme?.name ?? '')", $source);
        $this->assertStringContainsString(".toLocaleLowerCase('vi')", $source);
        $this->assertStringContainsString('filteredThemes.slice', $source);
        $this->assertStringContainsString('<Input.Search', $source);
        $this->assertStringContainsString('<Pagination', $source);
        $this->assertStringContainsString('pageSizeOptions={THEME_PAGE_SIZES}', $source);
        $this->assertStringContainsString('Không tìm thấy theme phù hợp', $source);
    }
}
