<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\LandingPage;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Database\Seeders\Xd0313ContactBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Xd0313ContactBlockTest extends TestCase
{
    use RefreshDatabase;

    private function generateDemo(): LandingPage
    {
        $generator = app(ThemeDemoContentGenerator::class);
        $generator->generate('XD0313', $generator->presetsForTheme('XD0313')[0]['key']);

        return LandingPage::where('theme_key', 'XD0313')->where('is_home', true)->firstOrFail();
    }

    public function test_home_contact_block_is_editable_and_renders_the_supported_form_contract(): void
    {
        $page = $this->generateDemo();
        $block = $page->blocks()->where('block_type', 'landing_contact')->firstOrFail();
        $this->assertSame('lien-he', $block->anchor_id);
        $this->assertContains('landing_contact', array_column(app(LandingPageBuilder::class)->availableBlocks('XD0313'), 'block_type'));
        $response = $this->get('/vi')->assertOk()->assertSee('rx13-contact__shell', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $forms = $xpath->query('//section[@id="lien-he"]//form');
        $this->assertSame(1, $forms->length);
        $form = $forms->item(0);
        $this->assertSame(route('site.contact.submit', ['locale' => 'vi']), $form->getAttribute('action'));
        $this->assertSame('POST', $form->getAttribute('method'));
        foreach (['name', 'phone', 'email', 'subject', 'message', '_token'] as $field) {
            $this->assertSame(1, $xpath->query('.//*[@name="'.$field.'"]', $form)->length, $field);
        }
        $this->assertSame('contact', $xpath->query('.//input[@name="source"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('.//*[@name="captcha"]', $form)->length);
        $this->assertSame(0, $xpath->query('.//input[@name="phone"][@required]', $form)->length);
        foreach ($xpath->query('.//label', $form) as $label) {
            $this->assertSame(1, $xpath->query('.//*[@id="'.$label->getAttribute('for').'"]', $form)->length);
        }
        $block->update(['is_visible' => false]);
        $this->get('/vi')->assertOk()->assertDontSee('class="rx13-contact__form"', false);
    }

    public function test_home_form_keeps_invalid_values_then_saves_a_contact_inquiry_and_shows_confirmation(): void
    {
        Mail::fake();
        config(['session.serialization' => 'php']);
        $this->generateDemo();
        $home = route('site.home', ['locale' => 'vi']);
        $submit = route('site.contact.submit', ['locale' => 'vi']);
        $this->from($home)->post($submit, ['source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'invalid', 'message' => 'ngắn'])
            ->assertSessionHasErrors(['email', 'message']);
        $this->get($home)->assertOk()->assertSee('value="Khách tư vấn"', false)
            ->assertSee('role="alert"', false)->assertSee('aria-invalid="true"', false);
        $this->from($home)->post($submit, ['source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'journey@example.test', 'phone' => '0912345678', 'subject' => 'Tư vấn hành trình', 'message' => 'Tôi muốn trao đổi về kế hoạch cho hành trình sắp tới.'])
            ->assertRedirect($home)->assertSessionHas('contact_status');
        $this->assertDatabaseHas('contact_inquiries', ['website_key' => 'website-main', 'source' => 'contact', 'email' => 'journey@example.test', 'subject' => 'Tư vấn hành trình', 'locale' => 'vi']);
        $this->get($home)->assertOk()->assertSee('role="status"', false)->assertSee('Đã gửi yêu cầu liên hệ.');
    }

    public function test_contact_notification_can_be_delivered_by_the_queue_with_a_valid_reply_to(): void
    {
        config(['queue.default' => 'sync', 'mail.default' => 'array', 'mail.from.address' => 'noreply@example.test']);
        $this->generateDemo();
        $home = route('site.home', ['locale' => 'vi']);
        $this->from($home)->post(route('site.contact.submit', ['locale' => 'vi']), [
            'source' => 'contact', 'name' => 'Khách tư vấn', 'email' => 'journey@example.test',
            'subject' => 'Tư vấn hành trình', 'message' => 'Tôi cần trao đổi về kế hoạch cho hành trình sắp tới.',
        ])->assertRedirect($home)->assertSessionHas('contact_status');
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('journey@example.test', $message->getReplyTo()[0]->getAddress());
        $this->assertSame('Khách tư vấn', $message->getReplyTo()[0]->getName());
        $this->assertStringContainsString('Tư vấn hành trình', $message->getSubject());
    }

    public function test_seeder_adds_only_missing_home_blocks_and_preserves_customized_or_hidden_blocks(): void
    {
        $page = $this->generateDemo();
        $page->blocks()->where('block_type', 'landing_contact')->firstOrFail()->delete();
        $before = $page->blocks()->pluck('id')->all();
        $builder = app(LandingPageBuilder::class);
        $other = $builder->seedHome('other-site', 'XD0313');
        $custom = $other->blocks()->where('block_type', 'landing_contact')->firstOrFail();
        $custom->update(['is_visible' => false, 'anchor_id' => 'custom-contact']);
        $custom->data()->where('locale', 'vi')->update(['title' => 'Tiêu đề đã chỉnh']);
        $unrelated = LandingPage::create(['website_key' => 'other-site', 'theme_key' => 'NT502', 'is_home' => true, 'slug' => 'nt502-home', 'status' => 'published']);
        $this->seed(Xd0313ContactBlockSeeder::class);
        $this->seed(Xd0313ContactBlockSeeder::class);
        $this->assertSame(1, $page->blocks()->where('block_type', 'landing_contact')->count());
        $this->assertSame($before, $page->blocks()->where('block_type', '!=', 'landing_contact')->pluck('id')->all());
        $this->assertSame(1, $other->blocks()->where('block_type', 'landing_contact')->count());
        $this->assertFalse($custom->fresh()->is_visible);
        $this->assertSame('custom-contact', $custom->fresh()->anchor_id);
        $this->assertSame('Tiêu đề đã chỉnh', $custom->data()->where('locale', 'vi')->firstOrFail()->title);
        $this->assertSame(0, $unrelated->blocks()->count());
        $this->assertSame('website-main', app(SiteContext::class)->websiteKey());
    }
}
