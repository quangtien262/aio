<?php

namespace Tests\Feature;

use App\Models\Admin;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Customers\Jobs\BuildCustomerExport;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerProfiles;
use Tests\TestCase;

class CustomersModuleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private string $book;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin');
        $this->postJson('/admin/api/modules/customers/install')->assertOk();
        $this->postJson('/admin/api/modules/customers/enable')->assertOk();
        $this->book = $this->postJson('/admin/api/customers/books', ['name' => 'Khách hàng chung', 'code' => 'MAIN'], $this->key())->assertOk()->json('data.public_id');
    }

    private function key(?string $key = null): array
    {
        return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
    }

    private function create(array $data = []): array
    {
        return $this->postJson('/admin/api/customers/books/'.$this->book.'/profiles', $data + ['name' => 'Khách thử', 'phone' => '0901234567'], $this->key())->assertOk()->json('data');
    }

    public function test_module_profile_contacts_and_source_records_are_separate_from_login(): void
    {
        $before = DB::table('customers')->count();
        $p = $this->create(['contacts' => [['kind' => 'phone', 'value' => '0901 234 567', 'is_primary' => true], ['kind' => 'phone', 'value' => '+84 901234567'], ['kind' => 'email', 'value' => 'Secret@Example.test']], 'addresses' => [['line1' => '12 Test', 'kind' => 'shipping', 'is_default' => true, 'profile_id' => 999999]]]);
        $this->assertSame($before, DB::table('customers')->count());
        $this->assertSame('+84901234567', $p['phone_normalized']);
        $this->assertCount(2, $p['contacts']);
        $this->assertCount(1, $p['addresses']);
        $this->assertArrayNotHasKey('profile_id', $p['addresses'][0]);
        $this->create(['name' => 'Người thân cùng số']);
        $this->assertSame(2, DB::table('cust_profiles')->count());
        $this->getJson('/admin/api/customers/books/'.$this->book.'/profiles?q=0901234567')->assertOk()->assertJsonPath('data.total', 2);
    }

    public function test_profile_command_replay_version_and_journal_do_not_store_pii(): void
    {
        $key = $this->key('customer-create-12345');
        $path = '/admin/api/customers/books/'.$this->book.'/profiles';
        $payload = ['name' => 'Private Customer', 'email' => 'private@example.test'];
        $p = $this->postJson($path, $payload, $key)->assertOk()->json('data');
        $this->postJson($path, $payload, $key)->assertOk()->assertJsonPath('data.public_id', $p['public_id']);
        $this->assertSame(1, DB::table('cust_profiles')->count());
        $this->postJson($path, ['name' => 'Different'], $key)->assertConflict();
        $this->putJson($path.'/'.$p['public_id'], ['name' => 'Updated', 'expected_version' => 1], $this->key())->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson($path.'/'.$p['public_id'], ['name' => 'Stale', 'expected_version' => 1], $this->key())->assertConflict();
        $journal = DB::table('cust_commands')->get()->toJson();
        $this->assertStringNotContainsString('private@example.test', $journal);
        $this->assertStringNotContainsString('Private Customer', $journal);
    }

    public function test_archive_restore_and_merge_preserve_original_records_and_alias(): void
    {
        $source = $this->create(['name' => 'Source']);
        $target = $this->create(['name' => 'Target', 'phone' => '0987654321']);
        $base = '/admin/api/customers/books/'.$this->book.'/profiles/';
        $this->postJson($base.$source['public_id'].'/archive', ['expected_version' => 1, 'archived' => true], $this->key())->assertOk()->assertJsonPath('data.status', 'archived');
        $this->postJson($base.$source['public_id'].'/archive', ['expected_version' => 2, 'archived' => false], $this->key())->assertOk();
        $this->postJson($base.$source['public_id'].'/merge', ['expected_version' => 3, 'target_id' => $target['public_id'], 'target_version' => 1, 'reason' => 'Confirmed duplicate'], $this->key())->assertOk()->assertJsonPath('data.public_id', $target['public_id']);
        $this->assertDatabaseHas('cust_profiles', ['public_id' => $source['public_id'], 'status' => 'merged']);
        $this->getJson($base.$source['public_id'])->assertOk()->assertJsonPath('data.public_id', $target['public_id']);
        $this->assertSame(2, DB::table('cust_profiles')->count());
    }

    public function test_consent_is_append_only_scoped_and_requires_policy_for_grant(): void
    {
        $p = $this->create();
        $path = '/admin/api/customers/books/'.$this->book.'/profiles/'.$p['public_id'].'/consents';
        $v = ['purpose' => 'marketing', 'channel' => 'email', 'brand' => 'Brand A', 'state' => 'granted', 'expected_version' => 1];
        $this->postJson($path, $v, $this->key())->assertUnprocessable();
        $this->postJson($path, $v + ['policy_version' => '2026-10', 'evidence' => 'Private signed evidence'], $this->key())->assertOk()->assertJsonPath('data.version', 2);
        $v['state'] = 'withdrawn';
        $v['expected_version'] = 2;
        $this->postJson($path, $v, $this->key())->assertOk();
        $this->assertSame(2, DB::table('cust_consents')->count());
        $this->assertStringNotContainsString('Private signed evidence', DB::table('cust_consents')->value('evidence'));
    }

    public function test_typed_custom_fields_validation_and_immutable_type_when_used(): void
    {
        $path = '/admin/api/customers/books/'.$this->book;
        $field = ['namespace' => 'retail-pos', 'key' => 'segment', 'label' => 'Nhóm', 'type' => 'select', 'options' => ['A', 'B'], 'sensitive' => true];
        $this->postJson($path.'/fields', $field, $this->key())->assertOk();
        $p = $this->create(['custom_fields' => ['retail-pos.segment' => 'A']]);
        $this->assertSame('A', $p['custom_fields']['retail-pos.segment']);
        $this->putJson($path.'/profiles/'.$p['public_id'], ['expected_version' => 1, 'custom_fields' => ['retail-pos.segment' => 'C']], $this->key())->assertUnprocessable();
        $field['type'] = 'number';
        $field['expected_version'] = 1;
        $this->postJson($path.'/fields', $field, $this->key())->assertUnprocessable();
        $this->assertStringNotContainsString('"A"', DB::table('cust_field_values')->value('value'));
    }

    public function test_import_preview_does_not_write_and_commit_is_idempotent(): void
    {
        $path = '/admin/api/customers/books/'.$this->book.'/imports';
        $rows = [['name' => 'One', 'code' => 'ONE'], ['name' => 'Two', 'code' => 'TWO']];
        $this->postJson($path, ['rows' => $rows], $this->key())->assertOk()->assertJsonPath('data.valid', true);
        $this->assertSame(0, DB::table('cust_profiles')->count());
        $key = $this->key('import-customer-123');
        $this->postJson($path, ['rows' => $rows, 'commit' => true], $key)->assertOk()->assertJsonPath('data.created_count', 2);
        $this->postJson($path, ['rows' => $rows, 'commit' => true], $key)->assertOk()->assertJsonPath('data.created_count', 2);
        $this->assertSame(2, DB::table('cust_profiles')->count());
    }

    public function test_export_is_private_and_download_is_reauthorized(): void
    {
        $this->create(['name' => '=FORMULA()']);
        $id = $this->postJson('/admin/api/customers/books/'.$this->book.'/exports', [], $this->key())->assertOk()->json('data.public_id');
        // afterCommit queues are dispatched when the surrounding test transaction is committed; invoke worker explicitly here.
        (new BuildCustomerExport($id, 'website-main'))->handle(app(CustomerAccessService::class), app(CustomerProfiles::class));
        $this->getJson('/admin/api/customers/exports/'.$id)->assertOk()->assertJsonPath('data.status', 'ready');
        $response = $this->get('/admin/api/customers/exports/'.$id.'/download')->assertOk();
        $this->assertStringContainsString("'=FORMULA()", $response->streamedContent());
        $this->postJson('/admin/api/modules/customers/disable')->assertOk();
        $this->getJson('/admin/api/customers/exports/'.$id.'/download')->assertNotFound();
    }

    public function test_website_context_and_unknown_profile_fail_closed(): void
    {
        $p = $this->create();
        $this->getJson('/admin/api/customers/books/'.Str::uuid().'/profiles/'.$p['public_id'])->assertNotFound();
        $this->getJson('/admin/api/customers/books/'.$this->book.'/profiles/'.$p['public_id'], ['X-Website-Key' => 'unknown-website'])->assertNotFound();
    }
}
