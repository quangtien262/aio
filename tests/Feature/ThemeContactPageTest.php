<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ThemeContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_theme_has_a_working_contact_form(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Contact audit', 'website_type' => 'ecommerce', 'active_theme_key' => 'FOOT404', 'branding' => ['company_name' => 'Contact audit', 'support_hotline' => '0912345678', 'support_email' => 'support@example.test', 'support_location' => '25 Example Street']]);
        foreach (glob(base_path('themes/*/theme.json')) as $file) {
            $theme = basename(dirname($file));
            $profile->update(['active_theme_key' => $theme]);
            $response = $this->get('/vi/contact')->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@action="'.route('site.contact.submit', ['locale' => 'vi']).'"]');
            $this->assertGreaterThan(0, $forms->length, $theme);
            foreach (['name', 'email', 'message', '_token'] as $field) {
                $this->assertGreaterThan(0, $xpath->query('.//*[@name="'.$field.'"]', $forms->item(0))->length, "$theme: $field");
            }
            if ($folder = getenv('CONTACT_PREVIEW')) {
                if (! is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                file_put_contents($folder.'/'.$theme.'.html', $response->getContent());
            }
        }
    }

    public function test_contact_form_preserves_errors_and_displays_submission_confirmation(): void
    {
        Mail::fake();
        config(['session.serialization' => 'php']);
        app(ThemeDemoContentProviderRegistry::class)->forTheme('FOOT404')->generate('foot404-complete');
        $url = route('site.contact', ['locale' => 'vi']);
        $submit = route('site.contact.submit', ['locale' => 'vi']);
        $this->get($url)->assertOk()->assertSee('Kết nối với chúng tôi')->assertSee('tc-contact-name', false);
        $this->from($url)->post($submit, ['name' => 'Khách thử', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertSessionHasErrors(['email', 'message']);

        $this->get($url)->assertOk()->assertSee('Khách thử')->assertSee('role="alert"', false);
        $this->from($url)->post($submit, ['source' => 'contact', 'name' => 'Khách thử', 'email' => 'contact@example.test', 'message' => 'Tôi cần tư vấn về bể thủy sinh.'])
            ->assertRedirect($url)->assertSessionHas('contact_status');
        $this->get($url)->assertOk()->assertSee('Đã gửi yêu cầu liên hệ.')->assertSee('role="status"', false);
        $this->assertDatabaseHas('contact_inquiries', ['email' => 'contact@example.test', 'source' => 'contact']);
    }
}
