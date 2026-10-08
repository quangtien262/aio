<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\ContactInquiry;
use App\Models\LandingPageBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Xd0320ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_contact_block_and_modal_use_shared_ajax_form_with_unique_labels(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0320', $generator->presetsForTheme('XD0320')[0]['key']);
        $block = LandingPageBlock::where('block_type', 'landing_contact')->firstOrFail();
        $this->assertSame('lien-he', $block->anchor_id);
        $response = $this->get('/vi')->assertOk()->assertSee('Trao đổi nhu cầu của bạn')
            ->assertSee('js/contact-validation.js')->assertSee('js/xd0320-contact.js');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $forms = $xpath->query('//form[contains(@class,"xd20-contact-form")]');
        $this->assertSame(2, $forms->length);
        foreach ($forms as $form) {
            $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $form->getAttribute('action'));
            $this->assertSame('POST', $form->getAttribute('method'));
            foreach (['name', 'phone', 'email', 'subject', 'message', '_token', 'source'] as $name) {
                $this->assertSame(1, $xpath->query('.//*[@name="'.$name.'"]', $form)->length);
            }
            foreach ($xpath->query('.//label', $form) as $label) {
                $this->assertSame(1, $xpath->query('//*[@id="'.$label->getAttribute('for').'"]')->length);
            }
        }
        $this->assertGreaterThan(0, $xpath->query('//a[@data-xd20-consultation-open][@aria-controls="xd20-consultation"]')->length);
        $block->data()->where('locale', 'en')->update(['translation_status' => 'published']);
        $this->get('/en')->assertOk()->assertSee('Request technical advice')->assertSee('/en/contact', false);
        $block->update(['is_visible' => false]);
        $this->get('/vi')->assertOk()->assertDontSee('class="xd20-contact-section"', false)->assertSee('data-xd20-consultation', false);
    }

    public function test_ajax_validation_rejects_invalid_fields_and_saves_valid_contact(): void
    {
        Mail::fake();
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0320', $generator->presetsForTheme('XD0320')[0]['key']);
        $url = route('site.contact.submit', ['locale' => 'vi']);
        $this->postJson($url, ['source' => 'contact', 'name' => '', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->assertSame(0, ContactInquiry::count());
        $this->postJson($url, ['source' => 'contact', 'name' => 'Khách tư vấn kỹ thuật', 'email' => 'industrial@example.test', 'phone' => '0912345678', 'subject' => 'Tư vấn nhà xưởng', 'message' => 'Tôi cần tư vấn nâng cấp hệ thống kỹ thuật nhà xưởng.'])
            ->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('contact_inquiries', ['website_key' => 'website-main', 'source' => 'contact', 'email' => 'industrial@example.test', 'subject' => 'Tư vấn nhà xưởng']);
    }
}
