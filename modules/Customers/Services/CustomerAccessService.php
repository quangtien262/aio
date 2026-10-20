<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Support\Facades\DB;

class CustomerAccessService
{
    public function actor(Admin $actor, bool $lock = false): Admin
    {
        $query = Admin::query()->whereKey($actor->id);
        $live = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($live && $live->isAvailable() && ! $live->must_change_password
            && (int) $live->auth_version === (int) $actor->auth_version, 403, 'Tài khoản hoặc phiên xác thực đã thay đổi.');
        abort_unless($live->canAccessWebsite($this->website()), 403);
        app(CustomerRuntimeState::class)->guard($lock);

        return $live;
    }

    public function website(): string
    {
        return app(SiteContext::class)->websiteKey();
    }

    public function permission(Admin $actor, string $permission): void
    {
        abort_unless($actor->canAccess($permission, 'website', $this->website()), 403, 'Không đủ quyền khách hàng.');
    }

    public function allowed(Admin $actor, object $book, string $permission, string $role = 'reader'): bool
    {
        if (! $actor->canAccess($permission, 'website', $this->website()) || $book->website_key !== $this->website()) {
            return false;
        }
        if ($actor->canAccess('customers.settings', 'global')) {
            return true;
        }
        $level = ['reader' => 1, 'editor' => 2, 'manager' => 3];
        $grant = DB::table('cust_book_grants')->where('book_id', $book->id)->where('admin_id', $actor->id)->value('role');

        return ($level[$grant] ?? 0) >= $level[$role];
    }

    public function book(Admin $actor, string $public, string $permission = 'customers.view', string $role = 'reader', bool $lock = false): object
    {
        $query = DB::table('cust_books')->where('public_id', $public)->where('website_key', $this->website())->where('status', 'active');
        $book = ($lock ? ($role === 'manager' ? $query->lockForUpdate() : $query->sharedLock()) : $query)->first();
        abort_unless($book, 404);
        abort_unless($this->allowed($actor, $book, $permission, $role), 403, 'Chưa được cấp quyền cho nhóm dữ liệu khách hàng này.');

        return $book;
    }
}
