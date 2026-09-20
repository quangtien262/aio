<?php

namespace Tests\Feature;

use App\Models\{Site, SiteProfile};
use App\Support\LegacyTextEncoding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Xd0310EncodingRepairTest extends TestCase
{
    use RefreshDatabase;

    private function broken(string $text): string
    {
        return mb_convert_encoding(mb_convert_encoding($text, 'UTF-8', 'Windows-1252'), 'UTF-8', 'Windows-1252');
    }

    public function test_repairs_double_encoding_without_changing_valid_text(): void
    {
        $repair = new LegacyTextEncoding;
        foreach (['Tư vấn doanh nghiệp', 'Logistics chủ động cho doanh nghiệp', 'Kết nối kho bãi, vận tải và giao nhận bằng một quy trình rõ ràng.', '344 Huỳnh Tấn Phát, Quận 7, TP.HCM'] as $text) {
            $this->assertSame($text, $repair->repair($this->broken($text)));
            $this->assertSame($text, $repair->repair($text));
        }
        $this->assertSame('Đúng rồi 😀 Tư vấn', $repair->repair('Đúng rồi 😀 '.$this->broken('Tư vấn')));
        $this->assertSame('Français Âge € https://example.com/a?q=1', $repair->repair('Français Âge € https://example.com/a?q=1'));
    }

    public function test_command_previews_then_repairs_only_selected_website_and_is_idempotent(): void
    {
        Site::create(['domain' => 'xd0310.test', 'website_key' => 'garden', 'theme_key' => 'XD0310', 'status' => 'active']);
        $bad = $this->broken('Tư vấn doanh nghiệp');
        $profile = SiteProfile::create(['website_key' => 'garden', 'site_name' => $bad, 'active_theme_key' => 'XD0310', 'branding' => ['support_location' => $bad]]);
        $other = SiteProfile::create(['website_key' => 'other', 'site_name' => $bad, 'active_theme_key' => 'XD0310']);
        $this->artisan('themes:repair-xd0310-encoding', ['domain' => 'xd0310.test'])->assertSuccessful();
        $this->assertSame($bad, DB::table('site_profiles')->where('id', $profile->id)->value('site_name'));
        $this->artisan('themes:repair-xd0310-encoding', ['domain' => 'xd0310.test', '--write' => true])->assertSuccessful();
        $this->assertSame('Tư vấn doanh nghiệp', DB::table('site_profiles')->where('id', $profile->id)->value('site_name'));
        $this->assertSame($bad, DB::table('site_profiles')->where('id', $other->id)->value('site_name'));
        $this->artisan('themes:repair-xd0310-encoding', ['domain' => 'xd0310.test'])->expectsOutput('0 bản ghi cần sửa. Dùng --write để lưu.')->assertSuccessful();
        $this->artisan('themes:repair-xd0310-encoding', ['domain' => 'unknown.test', '--write' => true])->assertFailed();
    }
}
