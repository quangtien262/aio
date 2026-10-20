<?php

namespace Modules\Customers\Services;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\Admin;
use App\Models\Customer;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAccountLinkService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerProfiles $profiles, private readonly CustomerCommandService $commands) {}

    public function invite(Admin $actor, string $book, string $profile, array $data, string $key): array
    {
        $v = validator($data, ['account_id' => 'required|integer|min:1'])->validate();

        return $this->commands->run($actor, 'account.invite', ['book' => $book, 'profile' => $profile] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.account.link', 'manager', true), function ($a, $b) use ($profile, $v) {
            $p = $this->profiles->find($b->id, $profile, true);
            abort_unless($p->type === 'person' && $p->status === 'active', 422, 'Liên kết tài khoản dành cho hồ sơ cá nhân đang hoạt động.');
            abort_unless(Customer::query()->whereKey($v['account_id'])->exists(), 422, 'Tài khoản website không tồn tại.');
            $token = Str::random(64);
            $public = (string) Str::uuid();
            DB::table('cust_account_link_requests')->where('profile_id', $p->id)->whereNull('consumed_at')->update(['expires_at' => now()]);
            DB::table('cust_account_link_requests')->insert(['public_id' => $public, 'profile_id' => $p->id, 'account_id' => $v['account_id'], 'token_hash' => hash('sha256', $token), 'issued_by' => $a->id, 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
            $this->profiles->event($p, 'customer.account.invited.v1', $a->id, ['account_link']);

            return ['public_id' => $public, 'token' => $token, 'expires_at' => now()->addMinutes(30)->toISOString(), 'one_time' => true];
        });
    }

    public function accept(Customer $account, string $token, string $password): array
    {
        validator(['token' => $token, 'password' => $password], ['token' => 'required|string|size:64', 'password' => 'required|string|max:255'])->validate();

        return DB::transaction(function () use ($account, $token, $password) {
            app(CustomerRuntimeState::class)->guard();
            $live = Customer::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($password, $live->password), 422, 'Mật khẩu hiện tại không đúng.');
            $invitation = DB::table('cust_account_link_requests')->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($invitation && ! $invitation->consumed_at && now()->lessThan($invitation->expires_at) && (int) $invitation->account_id === (int) $live->id, 422, 'Lời mời không hợp lệ hoặc đã hết hạn.');
            $p = DB::table('cust_profiles')->where('id', $invitation->profile_id)->lockForUpdate()->first();
            $b = DB::table('cust_books')->find($p->book_id);
            abort_unless($b && $b->website_key === app(SiteContext::class)->websiteKey() && $b->status === 'active' && $p->status === 'active', 404);
            $issuer = Admin::query()->find($invitation->issued_by);
            abort_unless($issuer && $issuer->isAvailable() && $this->access->allowed($issuer, $b, 'customers.account.link', 'manager'), 403, 'Quyền cấp lời mời đã bị thu hồi.');
            $old = DB::table('cust_account_links')->where('book_id', $b->id)->where('account_id', $live->id)->first();
            abort_if($old && (int) $old->profile_id !== (int) $p->id, 409, 'Tài khoản đã liên kết hồ sơ khác trong nhóm.');
            DB::table('cust_account_links')->updateOrInsert(['book_id' => $b->id, 'account_id' => $live->id], ['profile_id' => $p->id, 'verification_method' => 'admin_invitation_password', 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('cust_account_link_requests')->where('id', $invitation->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
            $this->profiles->event($p, 'customer.account.linked.v1', 0, ['account_link']);

            return ['linked' => true, 'profile_id' => $p->public_id, 'book_id' => $b->public_id];
        }, 3);
    }

    public function profiles(Customer $account): array
    {
        if (! app(ModuleCapabilityChecker::class)->has('customers', 'customers.profiles.read.v1')) {
            return [];
        }

        return DB::table('cust_account_links as l')->join('cust_books as b', 'b.id', '=', 'l.book_id')->join('cust_profiles as p', 'p.id', '=', 'l.profile_id')->where('l.account_id', $account->id)->where('b.website_key', app(SiteContext::class)->websiteKey())->where('b.status', 'active')->where('p.status', 'active')->get(['p.public_id', 'b.public_id as book_id', 'b.name as book_name'])->map(fn ($p) => (array) $p)->all();
    }

    private function linked(Customer $account, string $id, bool $lock = false): object
    {
        app(CustomerRuntimeState::class)->guard($lock);
        $q = DB::table('cust_profiles as p')->join('cust_account_links as l', 'l.profile_id', '=', 'p.id')->join('cust_books as b', 'b.id', '=', 'l.book_id')->where('l.account_id', $account->id)->where('p.public_id', $id)->where('b.website_key', app(SiteContext::class)->websiteKey())->where('b.status', 'active')->where('p.status', 'active');
        $p = ($lock ? $q->lockForUpdate() : $q)->first('p.*');
        abort_unless($p, 404);

        return $p;
    }

    public function show(Customer $account, string $id): array
    {
        return $this->selfResource($this->linked($account, $id));
    }

    private function selfResource(object $p): array
    {
        return array_intersect_key($this->profiles->resource($p, true), array_flip(['public_id', 'id', 'code', 'name', 'version', 'phone', 'email', 'birthday', 'contacts', 'addresses', 'consents']));
    }

    public function update(Customer $account, string $id, array $data): array
    {
        $v = validator($data, ['name' => 'sometimes|string|max:255', 'phone' => 'nullable|string|max:40', 'addresses' => 'sometimes|array|max:20', 'expected_version' => 'required|integer|min:1'])->validate();

        return DB::transaction(function () use ($account, $id, $v) {
            $p = $this->linked($account, $id, true);
            $this->profiles->save($p->book_id, $v, $id, 0, 'website-account');

            return $this->selfResource($this->profiles->find($p->book_id, $id));
        }, 3);
    }

    public function consent(Customer $account, string $id, array $data): array
    {
        $v = validator($data, ['purpose' => 'required|string|max:80', 'channel' => 'required|in:email,sms,phone,all', 'brand' => 'required|string|max:100', 'state' => 'required|in:unknown,granted,denied,withdrawn', 'policy_version' => 'nullable|required_if:state,granted|string|max:80', 'expected_version' => 'required|integer|min:1'])->validate();

        return DB::transaction(function () use ($account, $id, $v) {
            $p = $this->linked($account, $id, true);
            app(CustomerService::class)->version($p, $v['expected_version']);
            app(CustomerService::class)->appendConsent($p, $v, 0, 'website-account');

            return $this->selfResource($this->profiles->find($p->book_id, $id));
        }, 3);
    }
}
