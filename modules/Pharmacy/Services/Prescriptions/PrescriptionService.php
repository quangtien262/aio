<?php

namespace Modules\Pharmacy\Services\Prescriptions;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\Dispensing\TransactionSupport;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PrescriptionService extends TransactionSupport
{
    public function save(Admin $actor, array $input, string $key): array
    {
        return $this->create($actor, $input, $key);
    }

    public function create(Admin $actor, array $input, string $key): array
    {
        return $this->command($actor, 'prescription.save', $input, $key,
            fn (Admin $live) => $this->pharmacy($live, $input, 'pharmacy.prescription.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->pharmacy($live, $input, 'pharmacy.prescription.manage', true);
                $data = validator($input, [
                    'patient_id' => 'required|string', 'reference' => 'required|string|max:100',
                    'issued_at' => 'required|date_format:Y-m-d', 'expires_at' => 'nullable|date_format:Y-m-d',
                    'prescriber_name' => 'required|string|max:255', 'facility_name' => 'required|string|max:255',
                    'original_reference' => 'required|string|max:1000',
                    'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|string',
                    'lines.*.quantity' => 'required', 'lines.*.unit' => 'nullable|string|max:50',
                    'lines.*.instructions' => 'required|string|max:4000',
                ])->validate();
                $patient = $this->scopedRow('ph_patients', $data['patient_id'], $p, true);
                abort_unless($patient->status === 'active', 409, 'Hồ sơ người bệnh không hoạt động.');
                $issued = CarbonImmutable::parse($data['issued_at'], $p->timezone)->startOfDay();
                abort_if($issued->isFuture(), 422, 'Ngày kê đơn không được ở tương lai.');
                // Conservative five calendar days including the issue date. Shorter validity may be recorded.
                $maximum = $issued->addDays(4)->endOfDay();
                $expires = isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'], $p->timezone)->endOfDay() : $maximum;
                abort_if($expires->lessThan($issued) || $expires->greaterThan($maximum), 422, 'Hiệu lực đơn vượt giới hạn lĩnh thuốc đang áp dụng.');
                abort_if(DB::table('ph_prescriptions')->where('chain_id', $p->chain_id)->where('reference', $data['reference'])->exists(), 409, 'Mã đơn đã được tiếp nhận trong chuỗi; tra cứu đơn hiện có.');
                $snapshot = ['patient' => $this->decrypt($patient->encrypted_profile), 'prescriber_name' => $data['prescriber_name'], 'facility_name' => $data['facility_name'], 'original_reference' => $data['original_reference'], 'validity_policy' => 'five_calendar_days_inclusive_v1'];
                $rx = $this->insert('ph_prescriptions', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'patient_id' => $patient->id,
                    'reference' => $data['reference'], 'issued_at' => $issued->utc(), 'expires_at' => $expires->utc(), 'encrypted_snapshot' => $this->encrypt($snapshot)]);
                foreach ($data['lines'] as $line) {
                    $product = $this->product($p, $line['product_id'], true);
                    $quantity = $this->quantity($line['quantity']);
                    $this->increment($product, $quantity);
                    abort_if(isset($line['unit']) && $line['unit'] !== $product->base_unit, 422, 'Lượng trên đơn phải dùng đơn vị cơ sở của thuốc.');
                    $this->insert('ph_prescription_lines', ['prescription_id' => $rx->id, 'product_id' => $product->id, 'quantity_allowed' => $quantity, 'unit' => $product->base_unit, 'encrypted_instructions' => $this->encrypt($line['instructions'])]);
                }
                $this->event($live, $p, 'prescription.received', $rx);

                return $this->serialize($rx, $p, $live);
            });
    }

    public function verify(Admin $actor, array $input, string $key): array
    {
        validator($input, ['prescription_id' => 'required|string', 'reason' => 'required|string|max:2000', 'verification_method' => 'required|in:manual_review'])->validate();

        return $this->command($actor, 'prescription.verify', $input, $key,
            function (Admin $live) use ($input): object {
                $p = $this->scopeFor($live, 'ph_prescriptions', $input['prescription_id'], 'pharmacy.prescription.manage');
                $this->access()->professional($live, $p, 'review');

                return $p;
            }, function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_prescriptions', $input['prescription_id'], 'pharmacy.prescription.manage', true);
                $this->access()->professional($live, $p, 'review', true);
                $rx = $this->scopedRow('ph_prescriptions', $input['prescription_id'], $p, true);
                $this->version($rx, $input);
                abort_unless($rx->status === 'draft', 409, 'Đơn đã được xác minh hoặc không còn được sửa.');
                abort_if(CarbonImmutable::parse($rx->expires_at, 'UTC')->isPast(), 409, 'Đơn đã hết hiệu lực.');
                $snapshot = $this->decrypt($rx->encrypted_snapshot);
                $snapshot['verification_reason'] = $input['reason'];
                $rx = $this->change('ph_prescriptions', $rx, ['status' => 'verified', 'verification_method' => 'manual_review', 'verified_by' => $live->id, 'verified_at' => now(), 'encrypted_snapshot' => $this->encrypt($snapshot)]);
                $this->event($live, $p, 'prescription.verified', $rx);

                return $this->serialize($rx, $p, $live);
            });
    }

    public function transfer(Admin $actor, array $input, string $key): array
    {
        validator($input, ['prescription_id' => 'required|string', 'target_pharmacy_id' => 'required|string', 'consent_evidence' => 'required|string|max:4000', 'consented_at' => 'required|date|before_or_equal:now', 'expires_at' => 'nullable|date|after:now'])->validate();

        return $this->command($actor, 'prescription.transfer', $input, $key,
            function (Admin $live) use ($input): object {
                $origin = $this->scopeFor($live, 'ph_prescriptions', $input['prescription_id'], 'pharmacy.prescription.transfer');
                $target = $this->access()->pharmacy($live, $input['target_pharmacy_id'], 'pharmacy.prescription.transfer');
                $this->transferPermissions($live, $origin);
                $this->transferPermissions($live, $target);

                return $origin;
            }, function (Admin $live) use ($input): array {
                $origin = $this->scopeFor($live, 'ph_prescriptions', $input['prescription_id'], 'pharmacy.prescription.transfer', true);
                $target = $this->access()->pharmacy($live, $input['target_pharmacy_id'], 'pharmacy.prescription.transfer', true);
                $this->transferPermissions($live, $origin, true);
                $this->transferPermissions($live, $target, true);
                abort_unless((int) $origin->chain_id === (int) $target->chain_id && (int) $origin->id !== (int) $target->id, 422, 'Chỉ chia sẻ đơn tới điểm khác trong cùng chuỗi.');
                $rx = $this->scopedRow('ph_prescriptions', $input['prescription_id'], $origin, true);
                $this->version($rx, $input);
                abort_unless($rx->status === 'verified' && CarbonImmutable::parse($rx->expires_at, 'UTC')->isFuture(), 409, 'Chỉ chuyển tiếp đơn đã xác minh còn hiệu lực.');
                $patient = DB::table('ph_patients')->where('id', $rx->patient_id)->lockForUpdate()->first();
                abort_unless($patient && $patient->status === 'active', 409, 'Hồ sơ người bệnh không còn hoạt động.');
                $expires = CarbonImmutable::parse($rx->expires_at, 'UTC');
                if (! empty($input['expires_at'])) {
                    $expires = $expires->min(CarbonImmutable::parse($input['expires_at'], $origin->timezone)->utc());
                }
                DB::table('ph_prescription_transfers')->where('prescription_id', $rx->id)->where('target_pharmacy_id', $target->id)->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
                $permit = $this->insert('ph_prescription_transfers', ['chain_id' => $origin->chain_id, 'pharmacy_id' => $origin->id, 'target_pharmacy_id' => $target->id, 'prescription_id' => $rx->id, 'patient_id' => $patient->id, 'patient_version' => $patient->version, 'actor_id' => $live->id, 'consented_at' => CarbonImmutable::parse($input['consented_at'], $origin->timezone)->utc(), 'expires_at' => $expires, 'encrypted_consent' => $this->encrypt(['evidence' => $input['consent_evidence'], 'purpose' => 'continue_prescription_at_named_pharmacy', 'target_pharmacy_id' => $target->public_id])]);
                $this->event($live, $origin, 'prescription.transfer-granted', $permit);

                return $this->ref($permit) + ['prescription_id' => $rx->public_id, 'target_pharmacy_id' => $target->public_id, 'expires_at' => (string) $expires];
            });
    }

    public function revokeTransfer(Admin $actor, array $input, string $key): array
    {
        validator($input, ['transfer_id' => 'required|string', 'reason' => 'required|string|max:2000'])->validate();

        return $this->command($actor, 'prescription.transfer-revoke', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_prescription_transfers', $input['transfer_id'], 'pharmacy.prescription.transfer'),
            function (Admin $live) use ($input): array {
                $origin = $this->scopeFor($live, 'ph_prescription_transfers', $input['transfer_id'], 'pharmacy.prescription.transfer', true);
                $permit = $this->scopedRow('ph_prescription_transfers', $input['transfer_id'], $origin, true);
                $this->version($permit, $input);
                abort_unless($permit->status === 'active', 409, 'Ủy quyền đã kết thúc.');
                $evidence = $this->decrypt($permit->encrypted_consent);
                $evidence['revocation'] = ['reason' => $input['reason'], 'actor_id' => $live->id, 'revoked_at' => now()->toIso8601String()];
                $permit = $this->change('ph_prescription_transfers', $permit, ['status' => 'revoked', 'encrypted_consent' => $this->encrypt($evidence)]);
                $this->event($live, $origin, 'prescription.transfer-revoked', $permit);

                return $this->ref($permit);
            });
    }

    /** One canonical Rx/claim aggregate is reused at the explicitly consented target. */
    public function accessible(Admin $actor, object $pharmacy, string $id, bool $lock = false): object
    {
        $rx = $this->row('ph_prescriptions', $id, $lock);
        abort_unless((int) $rx->chain_id === (int) $pharmacy->chain_id, 404);
        $patients = DB::table('ph_patients')->where('id', $rx->patient_id);
        $patient = ($lock ? $patients->sharedLock() : $patients)->first();
        abort_unless($patient && $patient->status === 'active', 409, 'Hồ sơ người bệnh không còn hoạt động.');
        if ((int) $rx->pharmacy_id !== (int) $pharmacy->id) {
            $this->transferPermissions($actor, $pharmacy, $lock);
            $query = DB::table('ph_prescription_transfers')->where('prescription_id', $rx->id)->where('target_pharmacy_id', $pharmacy->id)->where('status', 'active')->where('expires_at', '>', now())->where('patient_version', $patient?->version);
            abort_unless($patient && $patient->status === 'active' && ($lock ? $query->sharedLock() : $query)->exists(), 403, 'Chưa có đồng ý chia sẻ đơn còn hiệu lực tới nhà thuốc này.');
        }

        return $rx;
    }

    private function transferPermissions(Admin $actor, object $pharmacy, bool $lock = false): void
    {
        foreach (['pharmacy.prescription.transfer', 'pharmacy.prescription.view', 'pharmacy.patient.view', 'pharmacy.patient.sensitive'] as $permission) {
            $this->access()->pharmacy($actor, $pharmacy->public_id, $permission, $lock);
        }
    }

    public function canReadTransferred(?Admin $actor, object $pharmacy, string $id): bool
    {
        if (! $actor) {
            return false;
        }
        try {
            $this->accessible($actor, $pharmacy, $id);

            return true;
        } catch (HttpException $e) {
            if (! in_array($e->getStatusCode(), [403, 404, 409], true)) {
                throw $e;
            }

            return false;
        }
    }

    public function show(Admin $actor, string $id, ?string $pharmacyId = null): array
    {
        $p = $pharmacyId ? $this->access()->pharmacy($actor, $pharmacyId, 'pharmacy.prescription.view') : $this->scopeFor($actor, 'ph_prescriptions', $id, 'pharmacy.prescription.view');
        $this->readAudit($actor, 'prescription', $id);

        return $this->serialize($this->accessible($actor, $p, $id), $p, $actor);
    }

    public function list(Admin $actor, array $filters): array
    {
        $p = $this->pharmacy($actor, $filters, 'pharmacy.prescription.view');
        $this->readAudit($actor, 'prescription-list');
        $permitted = DB::table('ph_prescription_transfers as t')->join('ph_patients as p', 'p.id', '=', 't.patient_id')->whereColumn('p.version', 't.patient_version')->where('p.status', 'active')->where('t.target_pharmacy_id', $p->id)->where('t.status', 'active')->where('t.expires_at', '>', now())->select('t.prescription_id');
        $canTransfer = $this->canRead($actor, $p, 'pharmacy.prescription.transfer') && $this->canRead($actor, $p, 'pharmacy.patient.view') && $this->canRead($actor, $p, 'pharmacy.patient.sensitive');
        $query = DB::table('ph_prescriptions')->where('chain_id', $p->chain_id)->where(fn ($q) => $q->where('pharmacy_id', $p->id)->when($canTransfer, fn ($q) => $q->orWhereIn('id', $permitted)));
        $query = $this->filterList($query, $filters, $p, ['public_id', 'reference']);

        return $this->pageList($query, $filters, fn ($row) => $this->serialize($row, $p, $actor));
    }

    private function serialize(object $rx, object $p, Admin $actor): array
    {
        $patient = DB::table('ph_patients')->find($rx->patient_id);
        $canRead = $this->canRead($actor, $p, 'pharmacy.prescription.view');
        $snapshot = $canRead ? $this->decrypt($rx->encrypted_snapshot) : [];
        $originId = DB::table('ph_pharmacies')->where('id', $rx->pharmacy_id)->value('public_id');
        $transfers = [];
        if ($canRead && $this->canRead($actor, $p, 'pharmacy.prescription.transfer')) {
            $transfers = DB::table('ph_prescription_transfers')->where('prescription_id', $rx->id)->when((int) $rx->pharmacy_id !== (int) $p->id, fn ($q) => $q->where('target_pharmacy_id', $p->id))->orderByDesc('id')->get()->map(fn ($permit) => $this->ref($permit) + ['target_pharmacy_id' => DB::table('ph_pharmacies')->where('id', $permit->target_pharmacy_id)->value('public_id'), 'expires_at' => $permit->expires_at])->all();
        }

        return $this->ref($rx) + ['pharmacy_id' => $p->public_id, 'origin_pharmacy_id' => $originId, 'patient_id' => $patient?->public_id, 'reference' => $rx->reference,
            'patient_display_name' => $canRead && $this->canRead($actor, $p, 'pharmacy.patient.view') ? ($this->decrypt($patient->encrypted_profile)['name'] ?? null) : null,
            'transfers' => $transfers,
            'issued_at' => $rx->issued_at, 'expires_at' => $rx->expires_at, 'verification_method' => $rx->verification_method,
            'external_verification_status' => 'not_verified',
            'prescriber_name' => $snapshot['prescriber_name'] ?? null, 'facility_name' => $snapshot['facility_name'] ?? null,
            'original_reference' => $snapshot['original_reference'] ?? null,
            'lines' => DB::table('ph_prescription_lines')->where('prescription_id', $rx->id)->orderBy('id')->get()->map(function ($line) use ($canRead): array {
                $product = DB::table('ph_products')->find($line->product_id);
                $claimed = $this->decimalSum(DB::table('ph_dispense_claims')->where('prescription_line_id', $line->id)->where(fn ($q) => $q->whereIn('status', ['committed_pending_handover', 'dispensed'])->orWhere(fn ($q) => $q->where('status', 'reserved')->where('expires_at', '>', now()))), 'quantity');
                $remaining = BigDecimal::max(BigDecimal::of((string) $line->quantity_allowed)->minus($claimed), 0)->toScale(6);

                return ['id' => $line->public_id, 'product_id' => $product?->public_id, 'name' => $product?->name, 'quantity' => (string) $line->quantity_allowed, 'remaining_quantity' => (string) $remaining, 'unit' => $line->unit, 'instructions' => $canRead ? $this->decrypt($line->encrypted_instructions) : null];
            })->all()];
    }
}
