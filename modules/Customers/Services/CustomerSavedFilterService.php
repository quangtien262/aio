<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerSavedFilterService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerCommandService $commands) {}

    public function listing(Admin $actor, string $book): array
    {
        $a = $this->access->actor($actor);
        $b = $this->access->book($a, $book);

        return DB::table('cust_saved_filters')->where('book_id', $b->id)->where('admin_id', $a->id)->orderBy('name')->get()->map(fn ($r) => ['public_id' => $r->public_id, 'name' => $r->name, 'filters' => json_decode(Crypt::decryptString($r->filters), true, flags: JSON_THROW_ON_ERROR)])->all();
    }

    public function save(Admin $actor, string $book, array $data, string $key): array
    {
        $v = validator($data, ['name' => 'required|string|max:80', 'filters' => 'required|array:q,status,type,tag,per_page', 'filters.q' => 'nullable|string|max:128', 'filters.status' => 'nullable|in:active,archived,all,merged', 'filters.type' => 'nullable|in:person,company', 'filters.tag' => 'nullable|string|max:80', 'filters.per_page' => 'nullable|integer|between:1,100'])->validate();
        $r = $this->commands->run($actor, 'filter.save', ['book' => $book] + $v, $key, fn ($a) => $this->access->book($a, $book, 'customers.view', 'reader', true), function ($a, $b) use ($v) {
            $old = DB::table('cust_saved_filters')->where('book_id', $b->id)->where('admin_id', $a->id)->where('name', $v['name'])->first();
            abort_if(! $old && DB::table('cust_saved_filters')->where('book_id', $b->id)->where('admin_id', $a->id)->count() >= 50, 422, 'Mỗi danh bạ tối đa 50 bộ lọc cá nhân.');
            $public = $old?->public_id ?? (string) Str::uuid();
            DB::table('cust_saved_filters')->updateOrInsert(['book_id' => $b->id, 'admin_id' => $a->id, 'name' => $v['name']], ['public_id' => $public, 'filters' => Crypt::encryptString(json_encode($v['filters'], JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);

            return ['public_id' => $public];
        });
        foreach ($this->listing($actor, $book) as $filter) {
            if ($filter['public_id'] === $r['public_id']) {
                return $filter;
            }
        }abort(404);
    }

    public function delete(Admin $actor, string $book, string $public): array
    {
        return DB::transaction(function () use ($actor, $book, $public) {
            $a = $this->access->actor($actor, true);
            $b = $this->access->book($a, $book, 'customers.view', 'reader', true);
            $deleted = DB::table('cust_saved_filters')->where('book_id', $b->id)->where('admin_id', $a->id)->where('public_id', $public)->delete();
            abort_unless($deleted, 404);

            return ['deleted' => true];
        }, 3);
    }
}
