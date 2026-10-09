<?php

namespace Tests\Feature;

use App\Core\Themes\Demo\ThemeDemoContentProviderRegistry;
use App\Mail\ContactInquiryMail;
use App\Models\SiteProfile;
use App\Models\WebsiteLocale;
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
            $response->assertSee('js/auth-client.js', false)->assertSee('css/auth-client.css', false);
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@action="'.route('site.contact.submit', ['locale' => 'vi']).'"]');
            $scripts = $xpath->query('//script[@data-contact-validation]');
            $this->assertSame(1, $scripts->length, $theme);
            $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $scripts->item(0)->getAttribute('data-endpoint'), $theme);
            $this->assertSame(route('site.newsletter.subscribe', ['locale' => 'vi']), $scripts->item(0)->getAttribute('data-newsletter-endpoint'), $theme);
            foreach ($xpath->query('//form[.//input[@type="email"] and not(.//textarea) and not(.//input[@type="password"]) and not(.//input[@name="name"])]') as $newsletter) {
                if ($newsletter->hasAttribute('data-ser-newsletter-form')) {
                    continue;
                }
                $this->assertSame(parse_url(route('site.newsletter.subscribe', ['locale' => 'vi']), PHP_URL_PATH), parse_url($newsletter->getAttribute('action'), PHP_URL_PATH), "$theme newsletter");
                $this->assertSame('post', strtolower($newsletter->getAttribute('method')), "$theme newsletter method");
                $this->assertGreaterThan(0, $xpath->query('.//input[@name="email"]', $newsletter)->length, "$theme newsletter email");
                $this->assertGreaterThan(0, $xpath->query('.//input[@name="_token"]', $newsletter)->length, "$theme newsletter CSRF");
            }
            $this->assertGreaterThan(0, $forms->length, $theme);
            if (! in_array($theme, ['BOOK920', 'DN302', 'DN350', 'DN351'], true)) {
                $this->assertSame(1, $xpath->query('//main[contains(@class,"tc-contact-page")]//div[@class="tc-contact-overview"]/header/h1')->length, "$theme contact heading");
                $this->assertSame(1, $xpath->query('//div[@class="tc-contact-overview"]/aside[@aria-labelledby="tc-contact-info-title"]')->length, "$theme contact information");
            }
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

    public function test_contact_page_repairs_legacy_branding_and_omits_missing_contact_details(): void
    {
        $profile = SiteProfile::create(['site_name' => 'Contact audit', 'website_type' => 'services', 'active_theme_key' => 'XD0307', 'branding' => ['company_name' => 'Logistics Viá»‡t', 'support_location' => 'Hà Ná»™i']]);
        $this->get('/vi/contact')->assertOk()->assertSee('Logistics Việt')->assertSee('Hà Nội')
            ->assertDontSee('href="tel:', false)->assertDontSee('href="mailto:', false);
        $profile->update(['branding' => []]);
        $this->get('/vi/contact')->assertOk()->assertSee('tc-contact-name', false)
            ->assertDontSee('maps/search', false)->assertDontSee('id="tc-contact-info-title"', false);
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
        WebsiteLocale::where('locale', 'en')->update(['is_published' => true, 'is_enabled_for_editing' => true]);
        $this->postJson(route('site.contact.submit', ['locale' => 'en']), ['source' => 'contact', 'name' => 'English visitor', 'email' => 'ajax-en@example.test', 'message' => 'I would like to request a consultation.'])
            ->assertOk()->assertJsonPath('message', 'Your contact request has been sent successfully.');
        $this->assertDatabaseHas('contact_inquiries', ['email' => 'ajax-en@example.test', 'locale' => 'en']);
    }
}
