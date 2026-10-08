<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Xd0306ConsultationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hero_opens_a_shared_modal_with_unique_fields_and_ajax_support(): void
    {
        app(ThemeDemoContentGenerator::class)->generate('XD0306', 'xd0306-digital-agency');
        $response = $this->get('/vi')->assertOk()->assertSee('data-xd6-consultation-open', false)->assertSee('js/contact-validation.js');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//dialog[@id="xd6-consultation"]/form')->item(0);
        $this->assertNotNull($form);
        $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $form->getAttribute('action'));
        foreach (['name', 'email', 'message'] as $name) {
            $this->assertSame(1, $xpath->query('.//*[@name="'.$name.'"][@required]', $form)->length);
        }
        foreach ($xpath->query('.//label', $form) as $label) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$label->getAttribute('for').'"]')->length);
        }
        $this->get('/vi/contact')->assertOk()->assertSee('id="xd6-consultation"', false);
    }

    public function test_modal_request_validates_and_saves_through_the_contact_endpoint(): void
    {
        Mail::fake();
        app(ThemeDemoContentGenerator::class)->generate('XD0306', 'xd0306-digital-agency');
        $url = route('site.contact.submit', ['locale' => 'vi']);
        $this->postJson($url, ['source' => 'contact', 'name' => '', 'email' => 'invalid', 'message' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
        $this->postJson($url, ['source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'digital@example.test', 'message' => 'Tôi cần tư vấn thiết kế website doanh nghiệp.'])
            ->assertOk()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('contact_inquiries', ['source' => 'contact', 'email' => 'digital@example.test']);
    }
}
