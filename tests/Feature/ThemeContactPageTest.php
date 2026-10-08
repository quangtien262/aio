<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Mail\ContactInquiryMail;
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
            $response->assertSee('js/contact-validation.js', false)->assertSee('css/contact-validation.css', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@action="'.route('site.contact.submit', ['locale' => 'vi']).'"]');
            $scripts = $xpath->query('//script[@data-contact-validation]');
            $this->assertSame(1, $scripts->length, $theme);
            $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $scripts->item(0)->getAttribute('data-endpoint'), $theme);
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

    public function test_ajax_contact_submission_returns_errors_without_saving_and_success_without_redirecting(): void
    {
        Mail::fake();
        app(ThemeDemoContentProviderRegistry::class)->forTheme('FOOT404')->generate('foot404-complete');
        $url = route('site.contact.submit', ['locale' => 'vi']);
        $this->postJson($url, ['source' => 'contact', 'name' => 'Khách AJAX', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'message']);
        $this->assertDatabaseCount('contact_inquiries', 0);
        Mail::assertNothingQueued();
        $this->postJson($url, ['source' => 'contact', 'name' => 'Khách AJAX', 'email' => 'ajax@example.test', 'message' => 'Tôi muốn nhận tư vấn qua biểu mẫu AJAX.'])
            ->assertOk()->assertJsonPath('message', 'Yêu cầu liên hệ đã được gửi thành công.')
            ->assertJsonPath('data.email', 'ajax@example.test')->assertHeaderMissing('Location');
        $this->assertDatabaseCount('contact_inquiries', 1);
        $this->assertDatabaseHas('contact_inquiries', ['email' => 'ajax@example.test', 'source' => 'contact', 'locale' => 'vi']);
        Mail::assertQueued(ContactInquiryMail::class, 1);
    }
}
