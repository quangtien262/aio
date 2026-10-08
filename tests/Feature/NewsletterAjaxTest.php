<?php

namespace Tests\Feature;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Models\NewsletterSubscriber;
use App\Models\WebsiteLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_newsletter_blocks_are_connected_to_subscription_endpoint(): void
    {
        $generator = app(ThemeDemoContentGenerator::class);
        foreach (['EC903', 'EC905', 'EC916'] as $theme) {
            $generator->generate($theme, $generator->presetsForTheme($theme)[0]['key']);
            $response = $this->get('/vi')->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@action="'.route('site.newsletter.subscribe', ['locale' => 'vi']).'"]');
            $this->assertGreaterThan(0, $forms->length, $theme);
            $this->assertSame('POST', $forms->item(0)->getAttribute('method'));
            foreach (['email', '_token'] as $name) {
                $this->assertGreaterThan(0, $xpath->query('.//input[@name="'.$name.'"]', $forms->item(0))->length, "$theme $name");
            }
        }
    }

    public function test_invalid_email_returns_field_errors_without_creating_a_subscriber(): void
    {
        $this->postJson(route('site.newsletter.subscribe', ['locale' => 'vi']), ['email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_ajax_registration_is_localized_and_repeated_email_does_not_duplicate_subscribers(): void
    {
        $url = route('site.newsletter.subscribe', ['locale' => 'vi']);
        foreach (['NEWS@example.test', 'news@example.test'] as $email) {
            $this->postJson($url, ['email' => $email, 'source' => 'xd0313-footer'])
                ->assertOk()->assertJsonPath('message', 'Đã đăng ký nhận bản tin thành công.')
                ->assertJsonPath('data.email', 'news@example.test')->assertHeaderMissing('Location');
        }
        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertSame('xd0313-footer', NewsletterSubscriber::firstOrFail()->source);
        WebsiteLocale::where('locale', 'en')->update(['is_published' => true, 'is_enabled_for_editing' => true]);
        $this->postJson(route('site.newsletter.subscribe', ['locale' => 'en']), ['email' => 'en@example.test'])
            ->assertOk()->assertJsonPath('message', 'You have successfully subscribed to our newsletter.');
    }
}
