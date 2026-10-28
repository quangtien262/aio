<?php

namespace Modules\Pharmacy\Services\Documents;

use App\Models\Admin;
use App\Support\AuditLogger;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Modules\Pharmacy\Services\Dispensing\DispensingService;
use Modules\Pharmacy\Services\PharmacyData as D;
use Modules\Pharmacy\Services\Read\PharmacyReadService;
use Modules\Pharmacy\Services\Sales\PharmacySaleService;
use Symfony\Component\HttpFoundation\Response;

/** Private documents are encrypted at rest and authorized again on every download. */
final class PharmacyDocumentService
{
    public function upload(Admin $actor, array $input, UploadedFile $file, string $key): array
    {
        validator($input + ['file' => $file], ['pharmacy_id' => 'required|uuid', 'kind' => 'required|in:prescription,evidence', 'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'])->validate();
        $bytes = $file->get();
        $payload = ['pharmacy_id' => $input['pharmacy_id'], 'kind' => $input['kind'], 'checksum' => hash('sha256', $bytes)];

        return $this->commands()->run($actor, 'document.upload', $payload, $key,
            function ($live) use ($input) {
                $p = $this->access()->pharmacy($live, $input['pharmacy_id'], 'pharmacy.documents.manage', true);
                $this->access()->pharmacy($live, $p->public_id, 'pharmacy.patient.sensitive', true);

                return ['pharmacy' => $p, 'permission' => 'pharmacy.documents.manage'];
            }, function ($live, $scope) use ($input, $bytes, $file) {
                $row = $this->store($live, $scope['pharmacy'], $input['kind'], $bytes, $file->getMimeType(), 'document.'.($file->guessExtension() ?: 'bin'));

                return $this->reference($row);
            });
    }

    public function command(Admin $actor, string $action, array $input, string $key): array
    {
        $permission = $action === 'export.create' ? 'pharmacy.export' : 'pharmacy.documents.manage';

        return $this->commands()->run($actor, $action, $input, $key,
            fn ($live) => ['pharmacy' => $this->access()->pharmacy($live, $input['pharmacy_id'] ?? '', $permission, true), 'permission' => $permission],
            function ($live, $scope) use ($action, $input) {
                $p = $scope['pharmacy'];
                if ($action === 'export.create') {
                    validator($input, ['resource' => 'required|in:products,inventory,sales,reports', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'])->validate();
                    // Bounded interactive export; larger exports must use a reviewed background adapter.
                    $result = app(PharmacyReadService::class)->read($live, $input['resource'], array_replace($input, ['pharmacy_id' => $p->public_id, 'per_page' => 100]));
                    abort_if(($result['meta']['total'] ?? 0) > 100, 422, 'Bản xuất tương tác tối đa 100 bản ghi; thu hẹp bộ lọc.');
                    $rows = $result['records'] ?? [$result];
                    $stream = fopen('php://temp', 'r+');
                    $columns = $rows ? array_values(array_filter(array_keys($rows[0]), fn ($c) => ! is_array($rows[0][$c]) && ! is_object($rows[0][$c]))) : ['id'];
                    fwrite($stream, "\xEF\xBB\xBF");
                    fputcsv($stream, $columns, escape: '');
                    foreach ($rows as $row) {
                        fputcsv($stream, array_map(function ($c) use ($row) {
                            $v = (string) ($row[$c] ?? '');

                            return preg_match('/^[=+@\-\t\r]/u', $v) ? "'".$v : $v;
                        }, $columns), escape: '');
                    }
                    rewind($stream);
                    $bytes = stream_get_contents($stream);
                    fclose($stream);

                    $required = [match ($input['resource']) {
                        'inventory' => 'pharmacy.inventory.view', 'sales' => 'pharmacy.sale.view', 'reports' => 'pharmacy.report.view', default => 'pharmacy.view'
                    }];
                    if (array_intersect($columns, ['cost', 'unit_cost', 'total_cost', 'unit_cost_minor']) !== []) {
                        $required[] = 'pharmacy.cost.view';
                    }

                    return $this->reference($this->store($live, $p, 'export', $bytes, 'text/csv', 'pharmacy-'.$input['resource'].'.csv', now()->addHours((int) config('pharmacy.exports_retention_hours', 24)), ['resource' => $input['resource'], 'required_permissions' => $required]));
                }
                validator($input, ['kind' => 'required|in:receipt,dispensing-label,handover', 'source_id' => 'required|uuid', 'copies' => 'sometimes|integer|between:1,3'])->validate();
                $html = $this->printable($live, $p, $input['kind'], $input['source_id']);
                $path = 'prints/'.Str::uuid().'.enc';
                $this->disk()->put($path, Crypt::encryptString($html));
                $row = D::insert('ph_print_jobs', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $live->id, 'kind' => $input['kind'], 'source_id' => $input['source_id'], 'copies' => $input['copies'] ?? 1, 'path' => $path, 'checksum' => hash('sha256', $html), 'expires_at' => now()->addHours(24), 'status' => 'ready']);

                return ['id' => $row->public_id, 'status' => 'ready', 'version' => $row->version, 'download_url' => '/admin/api/pharmacy/print-jobs/'.$row->public_id];
            });
    }

    public function download(Admin $actor, string $id, bool $print = false): Response
    {
        $row = D::row($print ? 'ph_print_jobs' : 'ph_documents', $id);
        $p = $this->access()->pharmacy($actor, (int) $row->pharmacy_id, $print || $row->kind !== 'export' ? 'pharmacy.documents.manage' : 'pharmacy.export');
        abort_unless($row->status === ($print ? 'ready' : 'active') && (! $row->expires_at || now()->lt($row->expires_at)), 410, 'Tài liệu đã hết hạn.');
        if ($print) {
            // Revocation of clinical/sale access also revokes an existing print URL.
            $current = $this->printable($actor, $p, $row->kind, $row->source_id);
            abort_unless(hash_equals($row->checksum, hash('sha256', $current)), 409, 'Nguồn in đã thay đổi; tạo bản in mới từ hồ sơ hiện hành.');
        } elseif ($row->kind !== 'export') {
            $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.sensitive');
        } else {
            abort_unless((int) $row->actor_id === (int) $actor->id, 403, 'Bản xuất chỉ dành cho người yêu cầu.');
            $metadata = D::json(Crypt::decryptString($row->encrypted_name));
            abort_unless(isset($metadata['required_permissions']) && is_array($metadata['required_permissions']), 403, 'Bản xuất thiếu ngữ cảnh quyền; tạo lại bản xuất.');
            foreach ($metadata['required_permissions'] as $required) {
                $this->access()->pharmacy($actor, (int) $p->id, $required);
            }
        }
        abort_unless($this->disk()->exists($row->path), 410, 'Tài liệu không còn được lưu.');
        $bytes = Crypt::decryptString($this->disk()->get($row->path));
        abort_unless(hash_equals($row->checksum, hash('sha256', $bytes)), 409, 'Checksum tài liệu không khớp.');
        if ($print) {
            DB::table('ph_print_jobs')->where('id', $row->id)->increment('download_count');
        }
        app(AuditLogger::class)->record('pharmacy.document.download', 'pharmacy-document', null, ['resource' => $row->public_id, 'kind' => $row->kind], $actor, 'pharmacy', '', 'pharmacy');

        return response($bytes)->header('Content-Type', $print ? 'text/html; charset=utf-8' : $row->mime_type)
            ->header('Content-Disposition', ($print ? 'inline' : 'attachment').'; filename="'.($print ? 'pharmacy-print.html' : ($row->kind === 'export' ? 'pharmacy-export.csv' : 'pharmacy-document')).'"')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff')
            ->header('Referrer-Policy', 'no-referrer')->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'");
    }

    private function printable(Admin $actor, object $p, string $kind, string $source): string
    {
        $sale = null;
        if ($kind === 'receipt') {
            $sale = app(PharmacySaleService::class)->showSale($actor, $source);
            $sourceRow = D::row('ph_dispensings', $sale['dispensing_id'], $p->chain_id, $p->id);
            $d = ['pharmacy_id' => $p->public_id, 'handover_status' => $sourceRow->handed_over_at ? 'handed_over' : 'pending', 'lines' => DB::table('ph_dispensing_lines')->where('dispensing_id', $sourceRow->id)->where('status', 'active')->orderBy('id')->get()->map(function ($line): array {
                $snapshot = D::json(Crypt::decryptString($line->encrypted_snapshot));

                return ['name' => $snapshot['name'], 'strength' => $snapshot['strength'] ?? '', 'quantity' => $line->quantity, 'unit' => $line->unit, 'total_minor' => $line->total_minor];
            })->all()];
        } else {
            $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.view');
            $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.sensitive');
            $this->access()->professional($actor, $p, 'dispense');
            $d = app(DispensingService::class)->show($actor, $source);
            app(DispensingService::class)->assertPrintable($actor, $p, D::row('ph_dispensings', $source, $p->chain_id, $p->id));
            abort_unless(in_array($d['status'], ['reviewed', 'finalized', 'handed_over', 'partially_returned', 'returned'], true), 409, 'Chỉ in nhãn từ hồ sơ đã duyệt.');
        }
        abort_unless(($d['pharmacy_id'] ?? '') === $p->public_id, 403);
        if ($kind !== 'receipt' && ! empty($d['prescription_id'])) {
            $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.prescription.view');
            abort_if(collect($d['lines'])->contains(fn ($line) => $line['instructions'] === null), 403, 'Không có quyền đọc hướng dẫn đơn thuốc để in nhãn.');
        }
        $title = match ($kind) {
            'receipt' => 'Phiếu bán hàng', 'handover' => 'Phiếu bàn giao thuốc', default => 'Nhãn cấp thuốc'
        };
        $html = '<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.e($title).'</title><style>body{font:14px/1.5 system-ui;margin:24px;max-width:760px}h1{font-size:22px}table{width:100%;border-collapse:collapse}td,th{padding:8px;text-align:left;border-bottom:1px solid #ccc}@media print{body{margin:0}}</style><h1>'.e($title).'</h1><p>'.e($p->name).' · '.e($source).'</p><p>Trạng thái bàn giao: '.e($d['handover_status']).'</p>';
        if ($kind !== 'receipt') {
            $html .= '<p>Người nhận: '.e($d['patient_display_name'] ?? 'Khách OTC').'</p>';
        }
        $html .= '<table><tr><th>Thuốc</th><th>Số lượng</th><th>'.($kind === 'receipt' ? 'Thành tiền' : 'Hướng dẫn đã duyệt').'</th></tr>';
        foreach ($d['lines'] as $line) {
            $html .= '<tr><td>'.e($line['name'].' '.($line['strength'] ?? '')).'</td><td>'.e($line['quantity'].' '.$line['unit']).'</td><td>'.e($kind === 'receipt' ? number_format($line['total_minor']).' VND' : ($line['instructions'] ?? '')).'</td></tr>';
            if ($kind !== 'receipt') {
                foreach ($line['allocations'] as $allocation) {
                    $batch = DB::table('inv_batches')->find($allocation['batch_id']);
                    $html .= '<tr><td colspan="3">Lô '.e($batch?->batch_code).' · Hạn '.e($batch?->expires_at).' · '.e($allocation['quantity']).'</td></tr>';
                }
            }
        }
        $html .= '</table>';
        if ($sale) {
            $html .= '<p>Tổng: '.e(number_format($sale['total_minor'])).' VND</p><p>Phiếu bán hàng nội bộ. Hóa đơn điện tử có luồng phát hành riêng.</p>';
        }

        return $html.'</html>';
    }

    private function store(Admin $actor, object $p, string $kind, string $bytes, string $mime, string $name, mixed $expires = null, array $metadata = []): object
    {
        $path = 'documents/'.Str::uuid().'.enc';
        $this->disk()->put($path, Crypt::encryptString($bytes));

        return D::insert('ph_documents', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'kind' => $kind, 'path' => $path, 'mime_type' => $mime, 'size' => strlen($bytes), 'checksum' => hash('sha256', $bytes), 'encrypted_name' => Crypt::encryptString(D::encode(['filename' => $name] + $metadata)), 'expires_at' => $expires]);
    }

    public function pruneExpired(int $limit = 100): int
    {
        $count = 0;
        foreach (['ph_documents', 'ph_print_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->whereNotNull('expires_at')->where('expires_at', '<=', now())->whereIn('status', ['active', 'ready'])->orderBy('id')->limit(max(1, min(500, $limit)))->get() as $row) {
                // Clinical originals have no automatic expiration assigned by this service.
                if ($table === 'ph_documents' && $row->kind !== 'export') {
                    continue;
                }
                $this->disk()->delete($row->path);
                DB::table($table)->where('id', $row->id)->where('version', $row->version)->update(['status' => 'expired', 'version' => $row->version + 1, 'updated_at' => now()]);
                $count++;
            }
        }

        return $count;
    }

    private function reference(object $row): array
    {
        return ['id' => $row->public_id, 'status' => $row->status, 'version' => $row->version, 'download_url' => '/admin/api/pharmacy/documents/'.$row->public_id];
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('pharmacy.private_disk', 'pharmacy_private'));
    }

    private function access(): PharmacyAccessService
    {
        return app(PharmacyAccessService::class);
    }

    private function commands(): PharmacyCommandService
    {
        return app(PharmacyCommandService::class);
    }
}
