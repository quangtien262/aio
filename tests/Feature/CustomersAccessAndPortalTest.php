<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Support\SiteContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customers\Services\CustomerAccountLinkService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomersAccessAndPortalTest extends TestCase
{
    use RefreshDatabase;

    private Admin $owner;

    private string $book;

    private array $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = Admin::findOrFail(1);
        $this->owner->forceFill(['must_change_password' => false])->save();
        $this->actingAs($this->owner, 'admin');
        $this->postJson('/admin/api/modules/customers/install')->assertOk();
        $this->postJson('/admin/api/modules/customers/enable')->assertOk();
        $this->book = $this->postJson('/admin/api/customers/books', ['name' => 'Private', 'code' => 'PRIVATE'], $this->key())->assertOk()->json('data.public_id');
        $this->profile = $this->postJson('/admin/api/customers/books/'.$this->book.'/profiles', ['name' => 'PII Name', 'phone' => '0901234567', 'email' => 'secret@example.test', 'notes' => 'Internal only', 'addresses' => [['line1' => 'Private address']]], $this->key())->assertOk()->json('data');
    }

    private function key(?string $key = null): array
    {
        return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
    }

    private function actor(array $permissions, string $grant = 'reader'): Admin
    {
        $actor = Admin::factory()->create(['must_change_password' => false]);
        $role = Role::create(['key' => 'test-'.Str::random(8), 'name' => 'Test']);
        $role->permissions()->sync(Permission::whereIn('key', $permissions)->pluck('id'));
        $actor->roleAssignments()->create(['role_id' => $role->id, 'scope_type' => 'website', 'scope_value' => 'website-main']);
        $actor = $actor->fresh();
        if ($grant !== 'none') {
            DB::table('cust_book_grants')->insert(['book_id' => DB::table('cust_books')->where('public_id', $this->book)->value('id'), 'admin_id' => $actor->id, 'role' => $grant, 'created_at' => now(), 'updated_at' => now()]);
        }$this->withSession(['admin_auth_version' => $actor->auth_version]);

        return $actor;
    }

    public function test_native_permission_and_book_grant_are_both_required_and_reader_is_masked(): void
    {
        $reader = $this->actor(['customers.view', 'customers.lookup'], 'none');
        $this->actingAs($reader, 'admin');
        $this->getJson('/admin/api/customers/books')->assertOk()->assertJsonCount(0, 'data');
        $path = '/admin/api/customers/books/'.$this->book.'/profiles/'.$this->profile['public_id'];
        $this->getJson($path)->assertForbidden();
        DB::table('cust_book_grants')->insert(['book_id' => DB::table('cust_books')->where('public_id', $this->book)->value('id'), 'admin_id' => $reader->id, 'role' => 'reader', 'created_at' => now(), 'updated_at' => now()]);
        $r = $this->getJson($path)->assertOk()->assertJsonPath('data.phone', '***4567')->assertJsonPath('data.notes', null)->assertJsonPath('data.addresses', []);
        $this->assertStringNotContainsString('secret@example.test', $r->getContent());
        $this->assertStringNotContainsString('Private address', $r->getContent());
        $this->postJson('/admin/api/customers/books/'.$this->book.'/exports', [], $this->key())->assertForbidden();
    }

    public function test_replay_and_command_status_recheck_book_grants(): void
    {
        $a = $this->actor(['customers.view', 'customers.create'], 'editor');
        $this->actingAs($a, 'admin');
        $key = 'create-and-revoke-123';
        $path = '/admin/api/customers/books/'.$this->book.'/profiles';
        $this->postJson($path, ['name' => 'Created'], $this->key($key))->assertOk();
        $this->getJson('/admin/api/customers/commands/'.$key)->assertOk()->assertJsonPath('data.status', 'committed');
        DB::table('cust_book_grants')->where('admin_id', $a->id)->delete();
        $this->postJson($path, ['name' => 'Created'], $this->key($key))->assertForbidden();
        $this->getJson('/admin/api/customers/commands/'.$key)->assertForbidden();
    }

    public function test_invitation_requires_intended_account_password_and_is_single_use_without_changing_login(): void
    {
        $account = Customer::factory()->create(['password' => 'PortalPassword123!']);
        $other = Customer::factory()->create();
        $service = app(CustomerAccountLinkService::class);
        $invite = $this->postJson('/admin/api/customers/books/'.$this->book.'/profiles/'.$this->profile['public_id'].'/account-link-invitations', ['account_id' => $account->id], $this->key())->assertOk()->json('data');
        $this->assertArrayHasKey('token', $invite);
        $this->assertStringNotContainsString($invite['token'], DB::table('cust_account_link_requests')->get()->toJson());
        $this->assertStringNotContainsString($invite['token'], DB::table('cust_commands')->get()->toJson());
        try {
            $service->accept($other, $invite['token'], 'wrong-password');
            $this->fail('Wrong account cannot accept.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $email = $account->email;
        $password = $account->password;
        $result = $service->accept($account, $invite['token'], 'PortalPassword123!');
        $this->assertTrue($result['linked']);
        $this->assertSame($email, $account->fresh()->email);
        $this->assertSame($password, $account->fresh()->password);
        try {
            $service->accept($account, $invite['token'], 'PortalPassword123!');
            $this->fail('Consumed token cannot replay.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $self = $service->show($account, $this->profile['public_id']);
        $this->assertArrayNotHasKey('notes', $self);
        $this->assertArrayNotHasKey('sources', $self);
        $this->assertArrayNotHasKey('custom_fields', $self);
        $this->assertCount(1, $service->profiles($account));
        $this->assertCount(0, $service->profiles($other));
    }

    public function test_portal_cannot_access_an_unlinked_profile_or_switch_website(): void
    {
        $account = Customer::factory()->create(['password' => 'PortalPassword123!']);
        $service = app(CustomerAccountLinkService::class);
        $invite = $service->invite($this->owner, $this->book, $this->profile['public_id'], ['account_id' => $account->id], (string) Str::uuid());
        $service->accept($account, $invite['token'], 'PortalPassword123!');
        app(SiteContext::class)->set(null, 'another-website');
        $this->assertCount(0, $service->profiles($account));
        try {
            $service->show($account, $this->profile['public_id']);
            $this->fail('Cross website access denied.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_relationship_rejects_cross_book_and_self_reference(): void
    {
        $base = '/admin/api/customers/books/'.$this->book.'/profiles/'.$this->profile['public_id'];
        $this->postJson($base.'/relationships', ['related_profile_id' => $this->profile['public_id'], 'role' => 'contact', 'expected_version' => 1], $this->key())->assertUnprocessable();
        $another = $this->postJson('/admin/api/customers/books', ['name' => 'Another', 'code' => 'ANOTHER'], $this->key())->assertOk()->json('data.public_id');
        $p = $this->postJson('/admin/api/customers/books/'.$another.'/profiles', ['name' => 'Another'], $this->key())->assertOk()->json('data.public_id');
        $this->postJson($base.'/relationships', ['related_profile_id' => $p, 'role' => 'contact', 'expected_version' => 1], $this->key())->assertNotFound();
    }
}
