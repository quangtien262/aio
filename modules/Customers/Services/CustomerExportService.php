<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Customers\Jobs\BuildCustomerExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerExportService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerCommandService $commands) {}

    public function create(Admin $actor, string $book, string $key): array
    {
        $r = $this->commands->run($actor, 'export.create', ['book' => $book], $key, function ($a) use ($book) {
            $b = $this->access->book($a, $book, 'customers.export', 'editor', true);
            abort_unless($this->access->allowed($a, $b, 'customers.sensitive', 'editor'), 403);

            return $b;
        }, function ($a, $b) {
            $public = (string) Str::uuid();
            DB::table('cust_exports')->insert(['public_id' => $public, 'book_id' => $b->id, 'actor_id' => $a->id, 'auth_version' => $a->auth_version, 'status' => 'pending', 'expires_at' => now()->addHours(config('customers.export_retention_hours', 24)), 'created_at' => now(), 'updated_at' => now()]);
            BuildCustomerExport::dispatch($public, $this->access->website());

            return ['public_id' => $public, 'status' => 'pending'];
        });

        return $this->status($actor, $r['public_id']);
    }

    public function status(Admin $actor, string $id): array
    {
        $r = $this->authorized($actor, $id);

        return ['public_id' => $r->public_id, 'status' => $r->status, 'created_at' => $r->created_at, 'expires_at' => $r->expires_at, 'error' => $r->error];
    }

    public function download(Admin $actor, string $id): StreamedResponse
    {
        $r = $this->authorized($actor, $id);
        abort_unless($r->status === 'ready' && $r->path && Storage::disk('local')->exists($r->path), 409, 'File chưa sẵn sàng.');
        abort_unless(hash_equals($r->checksum, hash_file('sha256', Storage::disk('local')->path($r->path))), 409, 'File xuất không còn nguyên vẹn.');
        app(AuditLogger::class)->record('customers.export.downloaded', 'customer-export', after: ['export_id' => $id], actor: $actor, moduleKey: 'customers');

        return Storage::disk('local')->download($r->path, 'khach-hang-'.$id.'.csv', ['Cache-Control' => 'private, no-store']);
    }

    private function authorized(Admin $actor, string $id): object
    {
        $a = $this->access->actor($actor);
        $r = DB::table('cust_exports')->where('public_id', $id)->where('actor_id', $a->id)->first();
        abort_unless($r, 404);
        abort_if(now()->greaterThan($r->expires_at), 410, 'File xuất đã hết hạn.');
        abort_unless((int) $r->auth_version === (int) $a->auth_version, 403);
        $book = DB::table('cust_books')->find($r->book_id);
        $this->access->book($a, $book->public_id, 'customers.export', 'editor');
        abort_unless($this->access->allowed($a, $book, 'customers.sensitive', 'editor'), 403);

        return $r;
    }
}
