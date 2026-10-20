<?php

namespace Modules\Customers\Jobs;

use App\Models\Admin;
use App\Support\SiteContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Customers\Services\CustomerAccessService;
use Modules\Customers\Services\CustomerProfiles;
use Modules\Customers\Services\CustomerRuntimeState;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BuildCustomerExport implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public string $exportId, public string $websiteKey)
    {
        $this->afterCommit();
    }

    public function handle(CustomerAccessService $access, CustomerProfiles $profiles): void
    {
        $previous = app(SiteContext::class)->websiteKey();
        $previousSite = app(SiteContext::class)->site();
        $token = (string) Str::uuid();
        $stream = null;
        try {
            app(SiteContext::class)->set(null, $this->websiteKey);
            $export = DB::transaction(function () use ($token) {
                app(CustomerRuntimeState::class)->guard();
                $r = DB::table('cust_exports')->where('public_id', $this->exportId)->lockForUpdate()->first();
                if (! $r || in_array($r->status, ['ready', 'expired'], true)) {
                    return null;
                }
                if ($r->lease_token && $r->lease_expires_at && now()->lessThan($r->lease_expires_at)) {
                    if ($this->job) {
                        $this->release(30);
                    }

                    return null;
                }
                abort_if(now()->greaterThan($r->expires_at), 410);
                DB::table('cust_exports')->where('id', $r->id)->update(['status' => 'processing', 'lease_token' => $token, 'lease_expires_at' => now()->addMinutes(5), 'updated_at' => now()]);

                return $r;
            }, 3);
            if (! $export) {
                return;
            }
            $actor = Admin::query()->findOrFail($export->actor_id);
            abort_unless((int) $actor->auth_version === (int) $export->auth_version, 403);
            $actor = $access->actor($actor);
            $b = DB::table('cust_books')->find($export->book_id);
            $access->book($actor, $b->public_id, 'customers.export', 'editor');
            abort_unless($access->allowed($actor, $b, 'customers.sensitive', 'editor'), 403);
            $stream = tmpfile();
            abort_unless(is_resource($stream), 503);
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['code', 'type', 'name', 'phone', 'email', 'status'], ',', '"', '');
            DB::table('cust_profiles')->where('book_id', $b->id)->orderBy('id')->chunkById(200, function ($rows) use ($stream, $profiles) {
                foreach ($rows as $p) {
                    $r = $profiles->resource($p, true, false);
                    $values = array_map(function ($value) {
                        $value = (string) ($value ?? '');

                        return preg_match('/^[\s]*[=+@\-\t\r]/u', $value) ? "'".$value : $value;
                    }, [$r['code'], $r['type'], $r['name'], $r['phone'], $r['email'], $r['status']]);
                    fputcsv($stream, $values, ',', '"', '');
                }
            });
            $meta = stream_get_meta_data($stream);
            $checksum = hash_file('sha256', $meta['uri']);
            rewind($stream);
            $path = 'customers/exports/'.$export->public_id.'.'.$token.'.csv';
            Storage::disk('local')->put($path, $stream);
            fclose($stream);
            $stream = null;
            DB::transaction(function () use ($access, $export, $b, $token, $path, $checksum) {
                $live = Admin::query()->findOrFail($export->actor_id);
                abort_unless((int) $live->auth_version === (int) $export->auth_version, 403);
                $live = $access->actor($live, true);
                $access->book($live, $b->public_id, 'customers.export', 'editor', true);
                abort_unless($access->allowed($live, $b, 'customers.sensitive', 'editor'), 403);
                $r = DB::table('cust_exports')->where('id', $export->id)->lockForUpdate()->first();
                abort_unless($r && $r->lease_token === $token && now()->lessThan($r->expires_at), 409, 'Lease xuất dữ liệu đã thay đổi.');
                DB::table('cust_exports')->where('id', $export->id)->update(['status' => 'ready', 'path' => $path, 'checksum' => $checksum, 'lease_token' => null, 'lease_expires_at' => null, 'error' => null, 'updated_at' => now()]);
            }, 3);
        } catch (\Throwable $e) {
            if (isset($path)) {
                Storage::disk('local')->delete($path);
            }
            DB::table('cust_exports')->where('public_id', $this->exportId)->where('lease_token', $token)->update(['status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null, 'error' => 'Không thể xuất dữ liệu; kiểm tra quyền và trạng thái app.', 'updated_at' => now()]);
            if ($e instanceof HttpException && in_array($e->getStatusCode(), [403, 404, 409, 410], true)) {
                return;
            }throw $e;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }app(SiteContext::class)->set($previousSite, $previous);
        }
    }
}
