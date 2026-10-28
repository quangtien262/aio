<?php

namespace Modules\Pharmacy\Services\Dispensing;

use App\Models\Admin;
use App\Support\AuditLogger;
use App\Support\Pos\PosNumbers;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Pharmacy\Services\Access\PharmacyAccessService;
use Modules\Pharmacy\Services\Commands\PharmacyCommandService;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared transaction primitives. Clinical facts never enter command/audit payloads. */
abstract class TransactionSupport
{
    protected function access(): PharmacyAccessService
    {
        return app(PharmacyAccessService::class);
    }

    protected function command(Admin $actor, string $kind, array $input, string $key, callable $authorize, callable $effect): array
    {
        $permission = match ($kind) {
            'patient.save' => 'pharmacy.patient.manage',
            'patient.lookup' => 'pharmacy.patient.view',
            'prescription.save', 'prescription.verify' => 'pharmacy.prescription.manage',
            'prescription.transfer', 'prescription.transfer-revoke' => 'pharmacy.prescription.transfer',
            'dispensing.review' => 'pharmacy.dispensing.review',
            'dispensing.handover' => 'pharmacy.dispensing.handover',
            'dispensing.finalize' => 'pharmacy.sale.create',
            'dispensing.return', 'dispensing.refund' => 'pharmacy.return.manage',
            'shift.open', 'shift.close' => 'pharmacy.shift.manage',
            default => 'pharmacy.dispensing.manage',
        };

        return app(PharmacyCommandService::class)->run($actor, $kind, $input, $key,
            function (Admin $live) use ($authorize, $permission): mixed {
                $scope = $authorize($live);

                return is_object($scope) ? ['pharmacy' => $scope, 'permission' => $permission] : $scope;
            }, $effect);
    }

    protected function pharmacy(Admin $actor, array $input, string $permission, bool $lock = false): object
    {
        validator($input, ['pharmacy_id' => 'required|string|max:100'])->validate();

        return $this->access()->pharmacy($actor, $input['pharmacy_id'], $permission, $lock);
    }

    protected function row(string $table, mixed $id, bool $lock = false): object
    {
        abort_unless(is_string($id) && $id !== '', 422, 'Thiếu định danh hồ sơ.');
        $query = DB::table($table)->where('public_id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404);

        return $row;
    }

    protected function scopeFor(Admin $actor, string $table, string $id, string $permission, bool $lock = false): object
    {
        $record = $this->row($table, $id, $lock);
        $pharmacy = DB::table('ph_pharmacies')->find($record->pharmacy_id);
        abort_unless($pharmacy, 404);

        return $this->access()->pharmacy($actor, $pharmacy->public_id, $permission, $lock);
    }

    protected function scopedRow(string $table, string $id, object $pharmacy, bool $lock = false): object
    {
        $row = $this->row($table, $id, $lock);
        abort_unless((int) $row->chain_id === (int) $pharmacy->chain_id && (int) $row->pharmacy_id === (int) $pharmacy->id, 404);

        return $row;
    }

    protected function insert(string $table, array $data): object
    {
        $id = DB::table($table)->insertGetId(['public_id' => (string) Str::uuid(), ...$data, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table($table)->find($id);
    }

    protected function change(string $table, object $row, array $data): object
    {
        DB::table($table)->where('id', $row->id)->update([...$data, 'version' => $row->version + 1, 'updated_at' => now()]);

        return DB::table($table)->find($row->id);
    }

    protected function version(object $row, array $input): void
    {
        validator($input, ['expected_version' => 'required|integer|min:1'])->validate();
        abort_unless((int) $input['expected_version'] === (int) $row->version, 409, 'Hồ sơ đã thay đổi; tải lại trước khi tiếp tục.');
    }

    protected function quantity(mixed $value, bool $positive = true): string
    {
        return PosNumbers::quantity($value, $positive);
    }

    protected function money(mixed $value): int
    {
        return PosNumbers::money($value);
    }

    protected function amount(string $quantity, int $price): int
    {
        return PosNumbers::amount($quantity, $price);
    }

    protected function product(object $pharmacy, string $id, bool $lock = false): object
    {
        $product = $this->row('ph_products', $id, $lock);
        abort_unless((int) $product->chain_id === (int) $pharmacy->chain_id && $product->status === 'active', 404, 'Thuốc không thuộc danh mục được phép.');
        abort_if($product->controlled, 409, 'Thuốc kiểm soát đặc biệt chưa được nghiệm thu và đang bị khóa.');
        abort_if($product->cold_chain, 409, 'Thuốc chuỗi lạnh chưa được nghiệm thu và đang bị khóa.');

        return $product;
    }

    protected function increment(object $product, string $quantity): void
    {
        $increment = BigDecimal::of((string) $product->minimum_increment);
        abort_unless($increment->isPositive() && BigDecimal::of($quantity)->remainder($increment)->isZero(), 422, 'Số lượng không đúng bước cấp phát của thuốc.');
    }

    protected function encrypt(mixed $value): string
    {
        return Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    protected function decrypt(string $value): mixed
    {
        return json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function event(Admin $actor, object $pharmacy, string $type, object $record): void
    {
        // Outbox metadata is intentionally free of names, Rx contents and clinical facts.
        app(PharmacyCommandService::class)->emit($pharmacy, 'pharmacy.'.$type, 'pharmacy', $record->public_id, (int) $record->version, []);
    }

    protected function ref(object $row): array
    {
        return ['id' => $row->public_id, 'version' => (int) $row->version, 'status' => $row->status];
    }

    protected function decimalSum(Builder $query, string $column): BigDecimal
    {
        $total = BigDecimal::zero();
        foreach ($query->pluck($column) as $value) {
            $total = $total->plus((string) $value);
        }

        return $total;
    }

    protected function filterList(Builder $query, array $filters, object $pharmacy, array $searchColumns = ['public_id'], ?callable $searchRelated = null): Builder
    {
        validator($filters, ['status' => 'nullable|string|max:40', 'q' => 'nullable|string|max:255', 'query' => 'nullable|string|max:255', 'from' => 'nullable|date', 'to' => array_filter(['nullable', 'date', ! empty($filters['from']) ? 'after_or_equal:from' : null]), 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100'])->validate();
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        foreach (['from', 'to'] as $boundary) {
            if (empty($filters[$boundary])) {
                continue;
            }
            $value = (string) $filters[$boundary];
            $date = CarbonImmutable::parse($value, $pharmacy->timezone);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                $date = $boundary === 'from' ? $date->startOfDay() : $date->endOfDay();
            }
            $query->where('created_at', $boundary === 'from' ? '>=' : '<=', $date->utc());
        }
        $search = trim((string) ($filters['q'] ?? $filters['query'] ?? ''));
        if ($search !== '') {
            abort_unless($searchColumns !== [] || $searchRelated, 422, 'Tra cứu người bệnh theo số điện thoại chính xác bằng thao tác riêng.');
            $query->where(function (Builder $group) use ($search, $searchColumns, $searchRelated): void {
                foreach ($searchColumns as $column) {
                    $group->orWhere($column, 'like', '%'.$search.'%');
                }
                if ($searchRelated) {
                    $searchRelated($group, $search);
                }
            });
        }

        return $query;
    }

    protected function pageList(Builder $query, array $filters, callable $serialize): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['per_page'] ?? 30)));
        $total = (clone $query)->count();

        return ['records' => $query->orderByDesc('id')->forPage($page, $size)->get()->map($serialize)->all(), 'meta' => ['page' => $page, 'per_page' => $size, 'total' => $total]];
    }

    protected function readAudit(Admin $actor, string $kind, ?string $id = null): void
    {
        app(AuditLogger::class)->record('pharmacy.'.$kind.'.read', 'pharmacy-record', null, array_filter(['id' => $id, 'reason' => 'interactive_record_read']), $actor, 'pharmacy', '', 'pharmacy');
    }

    protected function canRead(?Admin $actor, object $pharmacy, string $permission): bool
    {
        if (! $actor) {
            return false;
        }
        try {
            $this->access()->pharmacy($actor, $pharmacy->public_id, $permission);

            return true;
        } catch (HttpException $e) {
            if (! in_array($e->getStatusCode(), [403, 404], true)) {
                throw $e;
            }

            return false;
        }
    }

    protected function invalid(string $field, string $message = 'Giá trị không hợp lệ hoặc vượt độ chính xác cho phép.'): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
