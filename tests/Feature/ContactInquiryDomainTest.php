<?php

namespace Tests\Feature;

use App\Mail\ContactInquiryMail;
use App\Models\ContactInquiry;
use App\Models\Order;
use App\Models\Site;
use App\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactInquiryDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_submission_is_attributed_to_the_site_resolved_from_the_request_host(): void
    {
        Mail::fake();

        $siteA = $this->createSite('a.test', 'website-a', 'contact-a@example.com');
        $this->createSite('b.test', 'website-b', 'contact-b@example.com');

        $this->from('http://a.test/vi/contact')->post('http://a.test/vi/contact', [
            'name' => 'Khach hang A',
            'email' => 'lead@example.com',
            'phone' => '0909123456',
            'source' => 'contact',
            'subject' => 'Tu van website A',
            'message' => 'Toi can duoc tu van chi tiet cho website A.',
        ])->assertRedirect('http://a.test/vi/contact');

        $inquiry = ContactInquiry::query()->firstOrFail();

        $this->assertSame($siteA->id, $inquiry->site_id);
        $this->assertSame('website-a', $inquiry->website_key);
        $this->assertSame('a.test', $inquiry->submitted_host);
        $this->assertSame('new', $inquiry->status);

        Mail::assertQueued(ContactInquiryMail::class, function (ContactInquiryMail $mail): bool {
            return $mail->hasTo('contact-a@example.com')
                && $mail->payload['website_key'] === 'website-a'
                && $mail->payload['submitted_host'] === 'a.test';
        });
    }

    public function test_quote_request_and_linked_order_use_the_resolved_website_key(): void
    {
        Mail::fake();
        $this->createSite('quote.test', 'website-quote', 'quote@example.com');

        $this->postJson('http://quote.test/vi/contact', [
            'name' => 'Khach bao gia',
            'email' => 'quote-lead@example.com',
            'phone' => '0909000000',
            'source' => 'quote_modal',
            'route_summary' => 'Tuyen A - B',
            'message' => 'Toi can nhan bao gia chi tiet cho tuyen nay.',
        ])->assertOk();

        $order = Order::query()->firstOrFail();
        $inquiry = ContactInquiry::query()->firstOrFail();

        $this->assertSame('website-quote', $order->website_key);
        $this->assertSame('website-quote', $inquiry->website_key);
        $this->assertSame($order->id, $inquiry->order_id);
    }

    private function createSite(string $domain, string $websiteKey, string $supportEmail): Site
    {
        $site = Site::query()->create([
            'domain' => $domain,
            'website_key' => $websiteKey,
            'theme_key' => 'DN302',
            'name' => $websiteKey,
            'status' => 'active',
        ]);

        SiteProfile::query()->withoutGlobalScope('current_website')->create([
            'website_key' => $websiteKey,
            'site_name' => $websiteKey,
            'active_theme_key' => 'DN302',
            'branding' => [
                'company_name' => $websiteKey,
                'support_email' => $supportEmail,
            ],
        ]);

        return $site;
    }
}
