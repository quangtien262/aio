<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Customers\Events\CustomerChanged;
use Modules\Customers\Jobs\BuildCustomerExport;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerOutboxService;
use Modules\Customers\Services\CustomerProfiles;
use Tests\TestCase;

class CustomersAdvancedTest extends TestCase
{
    use RefreshDatabase;

    private string $book;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $a = Admin::findOrFail(1);
        $a->forceFill(['must_change_password' => false])->save();
        $this->actingAs($a, 'admin');
        $this->postJson('/admin/api/modules/customers/install')->assertOk();
        $this->postJson('/admin/api/modules/customers/enable')->assertOk();
        $this->book = $this->postJson('/admin/api/customers/books', ['name' => 'Advanced', 'code' => 'ADVANCED'], $this->key())->assertOk()->json('data.public_id');
        $this->base = '/admin/api/customers/books/'.$this->book;
    }

    private function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    private function profile(array $v = []): array
    {
        return $this->postJson($this->base.'/profiles', $v + ['name' => 'Customer'], $this->key())->assertOk()->json('data');
    }

    public function test_required_extension_fields_cannot_be_bypassed_and_search_index_respects_classification(): void
    {
        $field = ['namespace' => 'customers', 'key' => 'reference', 'label' => 'Reference', 'type' => 'text', 'required' => true, 'searchable' => true];
        $this->postJson($this->base.'/fields', $field, $this->key())->assertOk();
        $this->postJson($this->base.'/profiles', ['name' => 'Missing'], $this->key())->assertUnprocessable();
        $p = $this->profile(['custom_fields' => ['customers.reference' => 'SEARCHABLE-VALUE']]);
        $this->getJson($this->base.'/profiles?q=SEARCHABLE')->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('SEARCHABLE-VALUE', DB::table('cust_field_values')->value('search_value'));
        $field['searchable'] = false;
        $field['sensitive'] = true;
        $field['expected_version'] = 1;
        $this->postJson($this->base.'/fields', $field, $this->key())->assertOk();
        $this->assertNull(DB::table('cust_field_values')->value('search_value'));
        $this->getJson($this->base.'/profiles?q=SEARCHABLE')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_saved_filters_are_encrypted_and_preserve_private_query_without_browser_storage(): void
    {
        $payload = ['name' => 'Khách cần xem', 'filters' => ['q' => 'private@example.test', 'status' => 'active', 'tag' => 'VIP']];
        $p = $this->postJson($this->base.'/saved-filters', $payload, $this->key())->assertOk()->json('data');
        $this->assertStringNotContainsString('private@example.test', DB::table('cust_saved_filters')->value('filters'));
        $this->getJson($this->base.'/saved-filters')->assertOk()->assertJsonPath('data.0.filters.q', 'private@example.test');
        $this->deleteJson($this->base.'/saved-filters/'.$p['public_id'])->assertOk();
        $this->assertSame(0, DB::table('cust_saved_filters')->count());
    }

    public function test_merge_selected_fields_keeps_aliases_and_requires_consent_reconfirmation(): void
    {
        $source = $this->profile(['name' => 'Source Name', 'phone' => '0901111222']);
        $target = $this->profile(['name' => 'Target Name', 'email' => 'target@example.test']);
        $this->postJson($this->base.'/profiles/'.$source['public_id'].'/consents', ['purpose' => 'marketing', 'channel' => 'email', 'brand' => 'Brand', 'state' => 'granted', 'policy_version' => 'v1', 'expected_version' => 1], $this->key())->assertOk();
        $merged = $this->postJson($this->base.'/profiles/'.$source['public_id'].'/merge', ['target_id' => $target['public_id'], 'expected_version' => 2, 'target_version' => 1, 'reason' => 'Confirmed same person', 'field_choices' => ['name' => 'source', 'contacts' => 'combine']], $this->key())->assertOk()->json('data');
        $this->assertSame('Source Name', $merged['name']);
        $this->assertCount(2, $merged['contacts']);
        $this->assertSame('unknown', $merged['consents'][0]['state']);
        $this->assertDatabaseHas('cust_profiles', ['public_id' => $source['public_id'], 'status' => 'merged']);
        $this->getJson($this->base.'/profiles/'.$target['public_id'].'/timeline')->assertOk()->assertJsonCount(5, 'data.items');
    }

    public function test_export_worker_obeys_live_lease_and_duplicate_delivery_keeps_same_artifact(): void
    {
        $this->profile();
        $id = $this->postJson($this->base.'/exports', [], $this->key())->assertOk()->json('data.public_id');
        DB::table('cust_exports')->where('public_id', $id)->update(['status' => 'processing', 'path' => null, 'checksum' => null, 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addMinute()]);
        $job = new BuildCustomerExport($id, 'website-main');
        $job->handle(app(CustomerAccessService::class), app(CustomerProfiles::class));
        $this->assertDatabaseHas('cust_exports', ['public_id' => $id, 'status' => 'processing', 'path' => null]);
        $this->travel(2)->minutes();
        $job->handle(app(CustomerAccessService::class), app(CustomerProfiles::class));
        $export = DB::table('cust_exports')->where('public_id', $id)->first();
        $this->assertSame('ready', $export->status);
        $job->handle(app(CustomerAccessService::class), app(CustomerProfiles::class));
        $this->assertSame($export->path, DB::table('cust_exports')->where('public_id', $id)->value('path'));
    }

    public function test_outbox_retries_same_event_identity_without_contact_payload(): void
    {
        $bookId = DB::table('cust_books')->where('public_id', $this->book)->value('id');
        DB::table('cust_bindings')->insert(['public_id' => (string) Str::uuid(), 'website_key' => 'website-main', 'book_id' => $bookId, 'module_key' => 'customers', 'scope_type' => 'website', 'scope_key' => 'website-main', 'abilities' => '[]', 'mode' => 'shared', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->profile(['phone' => '0901234567']);
        $events = [];
        $fail = true;
        Event::listen(CustomerChanged::class, function ($e) use (&$fail, &$events) {
            $events[] = $e;
            if ($fail) {
                throw new \RuntimeException('Simulated delivery failure');
            }
        });
        $service = app(CustomerOutboxService::class);
        $this->assertSame(1, $service->dispatch()['failed']);
        $this->travel(6)->minutes();
        $fail = false;
        $this->assertSame(1, $service->dispatch()['sent']);
        $this->assertSame($events[0]->eventId, $events[1]->eventId);
        $this->assertStringNotContainsString('0901234567', json_encode($events, JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('cust_outbox_deliveries', ['status' => 'delivered', 'attempts' => 2]);
    }
}
