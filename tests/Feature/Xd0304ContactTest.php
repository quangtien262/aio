<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\ContactInquiry;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Xd0304ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_homepage_has_accessible_contact_form_and_respects_visibility(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0304', 'xd0304-logistics');
        $block = LandingPageBlock::where('block_type', 'landing_contact')->firstOrFail();
        $this->assertSame('lien-he', $block->anchor_id);
        $response = $this->get('/vi')->assertOk()->assertSee('js/contact-validation.js')->assertSee('Gửi yêu cầu tư vấn');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $forms = $xpath->query('//form[contains(@class,"xd4-contact__form")]');
        $this->assertSame(2, $forms->length);
        $this->get('/vi/pj')->assertOk()->assertSee('id="xd4-consultation"', false)->assertSee('data-xd4-consultation-open', false);
        $form = $forms->item(0);
        $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $form->getAttribute('action'));
        foreach (['name', 'phone', 'email', 'subject', 'message', '_token', 'source'] as $name) {
            $this->assertSame(1, $xpath->query('.//*[@name="'.$name.'"]', $form)->length);
        }
        foreach ($xpath->query('.//label', $form) as $label) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$label->getAttribute('for').'"]')->length);
        }
        $block->data()->where('locale', 'en')->update(['translation_status' => 'published']);
        $this->get('/en')->assertOk()->assertSee('Request logistics advice')->assertSee('/en/contact', false);
        $block->update(['is_visible' => false]);
        $this->get('/vi')->assertOk()->assertDontSee('class="xd4-section xd4-contact"', false);
    }

    public function test_ajax_contact_rejects_invalid_fields_and_saves_valid_request(): void
    {
        Mail::fake();
        app(ThemeDemoContentGenerator::class)->generate('XD0304', 'xd0304-logistics');
        $url = route('site.contact.submit', ['locale' => 'vi']);
        $this->postJson($url, ['source' => 'contact', 'name' => '', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->assertSame(0, ContactInquiry::count());
        $this->postJson($url, ['source' => 'contact', 'name' => 'Khách logistics', 'email' => 'logistics@example.test', 'subject' => 'Vận chuyển hàng hóa', 'message' => 'Tôi cần tư vấn vận chuyển hàng từ Hà Nội đến Đà Nẵng.'])
            ->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('contact_inquiries', ['website_key' => 'website-main', 'source' => 'contact', 'email' => 'logistics@example.test']);
    }
}
