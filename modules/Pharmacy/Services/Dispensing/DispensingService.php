<?php

namespace Modules\Pharmacy\Services\Dispensing;

use App\Models\Admin;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Pharmacy\Services\PharmacyInventoryService;
use Modules\Pharmacy\Services\Prescriptions\PrescriptionService;

final class DispensingService extends TransactionSupport
{
    public function create(Admin $actor, array $input, string $key): array
    {
        return $this->command($actor, 'dispensing.create', $input, $key,
            fn (Admin $live) => $this->pharmacy($live, $input, 'pharmacy.dispensing.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->pharmacy($live, $input, 'pharmacy.dispensing.manage', true);
                validator($input, ['mode' => 'required|in:otc,rx', 'lines' => 'required|array|min:1|max:100', 'patient_id' => 'nullable|string', 'prescription_id' => 'nullable|string'])->validate();
                $rx = ! empty($input['prescription_id']) ? app(PrescriptionService::class)->accessible($live, $p, $input['prescription_id'], true) : null;
                $patient = ! $rx && ! empty($input['patient_id']) ? $this->scopedRow('ph_patients', $input['patient_id'], $p, true) : null;
                abort_if($input['mode'] === 'rx' && ! $rx, 422, 'Bán thuốc theo đơn phải chọn đơn đã tiếp nhận.');
                abort_if($input['mode'] === 'otc' && $rx, 422, 'Đơn thuốc phải đi theo luồng Rx.');
                if ($rx) {
                    $patient = DB::table('ph_patients')->find($rx->patient_id);
                    abort_if(! empty($input['patient_id']) && $input['patient_id'] !== $patient?->public_id, 422, 'Người bệnh khác với người trên đơn.');
                }
                abort_if($patient && $patient->status !== 'active', 409, 'Hồ sơ người bệnh không hoạt động.');
                $d = $this->insert('ph_dispensings', ['chain_id' => $p->chain_id, 'pharmacy_id' => $p->id, 'patient_id' => $patient?->id, 'prescription_id' => $rx?->id, 'mode' => $input['mode'], 'created_by' => $live->id, 'created_auth_version' => $live->auth_version, 'encrypted_snapshot' => $this->encrypt(['patient' => $patient ? $this->decrypt($patient->encrypted_profile) : null])]);
                $this->writeLines($d, $p, $input['lines']);
                $this->event($live, $p, 'dispensing.created', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    public function replaceLines(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'lines' => 'required|array|min:1|max:100'])->validate();

        return $this->command($actor, 'dispensing.replace-lines', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless(in_array($d->status, ['draft', 'prepared', 'reviewed'], true), 409, 'Chỉ được sửa hồ sơ chưa chốt.');
                $this->release($live, $d, $p);
                DB::table('ph_dispensing_lines')->where('dispensing_id', $d->id)->update(['status' => 'replaced', 'updated_at' => now()]);
                DB::table('ph_dispensing_reviews')->where('dispensing_id', $d->id)->update(['invalidated_at' => now()]);
                $this->writeLines($d, $p, $input['lines']);
                $d = $this->change('ph_dispensings', $d, ['status' => 'draft', 'reservation_expires_at' => null]);
                $this->event($live, $p, 'dispensing.revised', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    public function prepare(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'allocations' => 'required|array|min:1|max:300', 'allocations.*.line_id' => 'required|string', 'allocations.*.batch_id' => 'required|integer|min:1', 'allocations.*.quantity' => 'required'])->validate();

        return $this->command($actor, 'dispensing.prepare', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless($d->status === 'draft', 409, 'Hồ sơ không còn ở bước chuẩn bị.');
                $this->validPrescription($live, $d, $p);
                $lines = $this->lines($d);
                $lineIds = $lines->pluck('public_id')->all();
                foreach ($input['allocations'] as $allocation) {
                    abort_unless(in_array($allocation['line_id'], $lineIds, true), 422, 'Phân bổ lô không thuộc hồ sơ.');
                }
                $expires = CarbonImmutable::now()->addMinutes(15);
                foreach ($lines as $line) {
                    $product = $this->productById($p, $line->product_id, true);
                    $this->assertPackaging($line, $product);
                    $allocations = array_values(array_filter($input['allocations'], fn ($a) => $a['line_id'] === $line->public_id));
                    $sum = BigDecimal::zero();
                    foreach ($allocations as $a) {
                        $quantity = $this->quantity($a['quantity']);
                        $this->increment($product, $quantity);
                        $sum = $sum->plus($quantity);
                    }
                    abort_unless($sum->isEqualTo((string) $line->quantity), 422, 'Tổng phân bổ lô phải khớp lượng cấp của từng dòng.');
                    if ($line->prescription_line_id) {
                        $rxLine = DB::table('ph_prescription_lines')->where('id', $line->prescription_line_id)->lockForUpdate()->first();
                        DB::table('ph_dispense_claims')->where('prescription_line_id', $rxLine->id)->where('status', 'reserved')->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
                        $claimed = BigDecimal::zero();
                        foreach (DB::table('ph_dispense_claims')->where('prescription_line_id', $rxLine->id)->whereIn('status', ['reserved', 'committed_pending_handover', 'dispensed'])->lockForUpdate()->get() as $claim) {
                            $claimed = $claimed->plus((string) $claim->quantity);
                        }
                        abort_if($claimed->plus((string) $line->quantity)->isGreaterThan((string) $rxLine->quantity_allowed), 409, 'Lượng đã cấp và đang giữ vượt lượng trên đơn.');
                        DB::table('ph_dispense_claims')->insert(['prescription_line_id' => $rxLine->id, 'dispensing_line_id' => $line->id, 'quantity' => $line->quantity, 'status' => 'reserved', 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach ($allocations as $i => $a) {
                        $reservation = app(PharmacyInventoryService::class)->reserve($live, $p, $product, $this->quantity($a['quantity']), (int) $a['batch_id'], $d->public_id, 'ph:reserve:'.$line->public_id.':'.$d->version.':'.$i);
                        $reservationId = $reservation['public_id'] ?? $reservation['id'] ?? null;
                        abort_unless(is_string($reservationId), 500, 'Kho không trả định danh giữ hàng.');
                        if (! empty($reservation['expires_at'])) {
                            $expires = $expires->min(CarbonImmutable::parse($reservation['expires_at'], 'UTC'));
                        }
                        $this->insert('ph_dispensing_allocations', ['dispensing_line_id' => $line->id, 'batch_id' => (int) $a['batch_id'], 'reservation_id' => $reservationId, 'quantity' => $this->quantity($a['quantity'])]);
                    }
                }
                DB::table('ph_dispense_claims')->whereIn('dispensing_line_id', $lines->pluck('id'))->where('status', 'reserved')->update(['expires_at' => $expires]);
                $d = $this->change('ph_dispensings', $d, ['status' => 'prepared', 'reservation_expires_at' => $expires]);
                $this->event($live, $p, 'dispensing.prepared', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    public function review(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'decision' => 'required|in:approved,rejected', 'reason' => 'required|string|max:4000'])->validate();

        return $this->command($actor, 'dispensing.review', $input, $key,
            function (Admin $live) use ($input): object {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.review');
                $this->access()->professional($live, $p, 'review');

                return $p;
            }, function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.review', true);
                $this->access()->professional($live, $p, 'review', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless(in_array($d->status, ['prepared', 'reviewed'], true), 409, 'Phải chuẩn bị thuốc/lô trước khi duyệt.');
                $this->eligible($live, $d, $p, true, 'dispensing.review');
                DB::table('ph_dispensing_reviews')->where('dispensing_id', $d->id)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
                $this->insert('ph_dispensing_reviews', ['dispensing_id' => $d->id, 'reviewer_id' => $live->id, 'decision' => $input['decision'], 'fingerprint' => $this->fingerprint($d, $p), 'encrypted_reason' => $this->encrypt($input['reason'])]);
                $clinicalSnapshot = $this->decrypt($d->encrypted_snapshot);
                if ($d->patient_id) {
                    $patient = DB::table('ph_patients')->find($d->patient_id);
                    $clinicalSnapshot['patient'] = $this->decrypt($patient->encrypted_profile);
                    $clinicalSnapshot['patient_version'] = (int) $patient->version;
                }
                $d = $this->change('ph_dispensings', $d, ['status' => $input['decision'] === 'approved' ? 'reviewed' : 'prepared', 'encrypted_snapshot' => $this->encrypt($clinicalSnapshot)]);
                $this->event($live, $p, 'dispensing.reviewed', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    public function cancel(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'reason' => 'required|string|max:2000'])->validate();

        return $this->command($actor, 'dispensing.cancel', $input, $key,
            fn (Admin $live) => $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage'),
            function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.manage', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless(in_array($d->status, ['draft', 'prepared', 'reviewed'], true), 409, 'Hồ sơ đã chốt phải đi qua nhận lại và hoàn tiền.');
                $this->release($live, $d, $p);
                DB::table('ph_dispensing_reviews')->where('dispensing_id', $d->id)->update(['invalidated_at' => now()]);
                $this->insert('ph_custody_events', ['dispensing_id' => $d->id, 'actor_id' => $live->id, 'action' => 'cancelled', 'encrypted_evidence' => $this->encrypt(['reason' => $input['reason']])]);
                $d = $this->change('ph_dispensings', $d, ['status' => 'cancelled', 'reservation_expires_at' => null]);
                $this->event($live, $p, 'dispensing.cancelled', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    public function handover(Admin $actor, array $input, string $key): array
    {
        validator($input, ['dispensing_id' => 'required|string', 'recipient_name' => 'required|string|max:255', 'reason' => 'nullable|string|max:2000'])->validate();

        return $this->command($actor, 'dispensing.handover', $input, $key,
            function (Admin $live) use ($input): object {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.handover');
                $this->access()->professional($live, $p, 'handover');

                return $p;
            }, function (Admin $live) use ($input): array {
                $p = $this->scopeFor($live, 'ph_dispensings', $input['dispensing_id'], 'pharmacy.dispensing.handover', true);
                $this->access()->professional($live, $p, 'handover', true);
                $d = $this->scopedRow('ph_dispensings', $input['dispensing_id'], $p, true);
                $this->version($d, $input);
                abort_unless($d->status === 'finalized', 409, 'Hồ sơ chưa chốt hoặc đã giao/nhận lại.');
                abort_if(DB::table('ph_returns')->where('dispensing_id', $d->id)->exists(), 409, 'Đã có nhận lại; cần xử lý ngoại lệ thay vì giao toàn bộ.');
                $this->eligible($live, $d, $p, false, 'dispensing.handover');
                DB::table('ph_dispense_claims')->whereIn('dispensing_line_id', $this->lines($d)->pluck('id'))->where('status', 'committed_pending_handover')->update(['status' => 'dispensed', 'updated_at' => now()]);
                $this->insert('ph_custody_events', ['dispensing_id' => $d->id, 'actor_id' => $live->id, 'action' => 'handed_over', 'encrypted_evidence' => $this->encrypt(['recipient_name' => $input['recipient_name'], 'reason' => $input['reason'] ?? null])]);
                $d = $this->change('ph_dispensings', $d, ['status' => 'handed_over', 'handed_over_at' => now()]);
                $this->event($live, $p, 'dispensing.handed_over', $d);

                return $this->snapshot($d, $p, $live);
            });
    }

    /** Called only by the sale finalization inside the same command transaction. */
    public function assertFinalizable(Admin $actor, object $d, object $p): void
    {
        abort_unless($d->status === 'reviewed', 409, 'Hồ sơ cần được duyệt chuyên môn trước khi chốt.');
        $review = DB::table('ph_dispensing_reviews')->where('dispensing_id', $d->id)->where('decision', 'approved')->whereNull('invalidated_at')->latest('id')->first();
        abort_unless($review, 409, 'Hồ sơ thiếu phê duyệt chuyên môn.');
        // The reviewer must still hold a live professional credential, even if another person finalizes.
        $reviewer = Admin::query()->find($review->reviewer_id);
        abort_unless($reviewer && $reviewer->isAvailable(), 409, 'Người duyệt không còn hoạt động.');
        $this->access()->pharmacy($reviewer, $p->public_id, 'pharmacy.dispensing.review', true);
        $this->access()->professional($reviewer, $p, 'review', true);
        $this->access()->professional($reviewer, $p, 'dispense', true);
        // The cashier acts on money/stock; clinical access remains with the live reviewer.
        $this->eligible($actor, $d, $p, true, 'dispensing.finalize', $reviewer);
        abort_unless(hash_equals($review->fingerprint, $this->fingerprint($d, $p)), 409, 'Dữ liệu thuốc/đơn/người bệnh/giá đã đổi; cần duyệt lại.');
    }

    /** Called by private label creation/download; never changes stock or financial state. */
    public function assertPrintable(Admin $actor, object $p, object $d): void
    {
        $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.view', true);
        $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.patient.sensitive', true);
        $this->access()->professional($actor, $p, 'dispense', true);
        if ($d->prescription_id) {
            $this->access()->pharmacy($actor, $p->public_id, 'pharmacy.prescription.view', true);
        }
        abort_unless(in_array($d->status, ['reviewed', 'finalized', 'handed_over', 'partially_returned', 'returned'], true), 409, 'Chỉ in nhãn từ hồ sơ đã duyệt.');
        if ($d->status === 'reviewed') {
            $review = DB::table('ph_dispensing_reviews')->where('dispensing_id', $d->id)->where('decision', 'approved')->whereNull('invalidated_at')->latest('id')->first();
            abort_unless($review && hash_equals($review->fingerprint, $this->fingerprint($d, $p)), 409, 'Dữ liệu đã đổi sau duyệt; không được in nhãn chưa duyệt lại.');
            $reviewer = Admin::query()->find($review->reviewer_id);
            abort_unless($reviewer && $reviewer->isAvailable(), 409, 'Người duyệt không còn hoạt động.');
            $this->access()->pharmacy($reviewer, $p->public_id, 'pharmacy.dispensing.review', true);
            $this->access()->professional($reviewer, $p, 'review', true);
            $this->access()->professional($reviewer, $p, 'dispense', true);
        }
        $this->eligible($actor, $d, $p, $d->status === 'reviewed', 'dispensing.print');
    }

    public function show(Admin $actor, string $id): array
    {
        $p = $this->scopeFor($actor, 'ph_dispensings', $id, 'pharmacy.dispensing.view');
        $this->readAudit($actor, 'dispensing', $id);

        return $this->snapshot($this->scopedRow('ph_dispensings', $id, $p), $p, $actor);
    }

    public function list(Admin $actor, array $filters): array
    {
        $p = $this->pharmacy($actor, $filters, 'pharmacy.dispensing.view');
        $this->readAudit($actor, 'dispensing-list');
        $query = $this->filterList(DB::table('ph_dispensings')->where('pharmacy_id', $p->id), $filters, $p);

        return $this->pageList($query, $filters, fn ($r) => $this->snapshot($r, $p, $actor));
    }

    public function snapshot(object $d, object $p, ?Admin $actor = null): array
    {
        $sale = DB::table('ph_sales')->where('dispensing_id', $d->id)->first();
        $lines = $this->lines($d);
        $patientName = null;
        $clinicalPatient = null;
        $rx = $d->prescription_id ? DB::table('ph_prescriptions')->find($d->prescription_id) : null;
        $clinicalAccess = ! $rx || (int) $rx->pharmacy_id === (int) $p->id || app(PrescriptionService::class)->canReadTransferred($actor, $p, $rx->public_id);
        if ($clinicalAccess && $d->patient_id && $this->canRead($actor, $p, 'pharmacy.patient.view')) {
            if ($d->finalized_at || $d->status === 'reviewed') {
                $profile = $this->decrypt($d->encrypted_snapshot)['patient'] ?? [];
            } else {
                $patient = DB::table('ph_patients')->find($d->patient_id);
                $profile = $patient ? $this->decrypt($patient->encrypted_profile) : [];
            }
            $patientName = $profile['name'] ?? null;
            if ($this->canRead($actor, $p, 'pharmacy.patient.sensitive')) {
                $clinicalPatient = array_intersect_key($profile, array_flip(['date_of_birth', 'allergies', 'notes']));
            }
        }
        $canReadInstructions = $clinicalAccess && (! $d->prescription_id || $this->canRead($actor, $p, 'pharmacy.prescription.view'));

        return $this->ref($d) + ['pharmacy_id' => $p->public_id, 'mode' => $d->mode, 'created_at' => $d->created_at,
            'patient_id' => $d->patient_id ? DB::table('ph_patients')->where('id', $d->patient_id)->value('public_id') : null,
            'prescription_id' => $d->prescription_id ? DB::table('ph_prescriptions')->where('id', $d->prescription_id)->value('public_id') : null,
            'patient_display_name' => $patientName, 'clinical_patient' => $clinicalPatient, 'total_minor' => $lines->sum('total_minor'), 'sale_id' => $sale?->public_id,
            'review_status' => $d->status === 'reviewed' || $d->finalized_at ? 'approved' : 'pending',
            'inventory_status' => $d->finalized_at ? 'committed' : ($d->reservation_expires_at ? 'reserved' : 'unreserved'),
            'payment_status' => $sale?->status ?? 'unpaid', 'handover_status' => $d->handed_over_at ? 'handed_over' : 'pending',
            'reservation_expires_at' => $d->reservation_expires_at,
            'lines' => $lines->map(function ($line) use ($canReadInstructions): array {
                $snapshot = $this->decrypt($line->encrypted_snapshot);

                return ['id' => $line->public_id, 'product_id' => DB::table('ph_products')->where('id', $line->product_id)->value('public_id'),
                    'prescription_line_id' => $line->prescription_line_id ? DB::table('ph_prescription_lines')->where('id', $line->prescription_line_id)->value('public_id') : null,
                    'name' => $snapshot['name'], 'quantity' => (string) $line->quantity, 'sale_quantity' => (string) $line->sale_quantity,
                    'packaging_id' => $line->packaging_id, 'packaging_name' => $snapshot['packaging_name'] ?? null, 'packaging_code' => $snapshot['packaging_code'] ?? null,
                    'packaging_revision' => $snapshot['packaging_revision'] ?? null, 'quantity_multiplier' => $snapshot['quantity_multiplier'] ?? (string) BigDecimal::of((string) $line->quantity)->dividedBy((string) $line->sale_quantity, 6),
                    'sale_unit' => $snapshot['sale_unit'] ?? $line->unit,
                    'strength' => $snapshot['strength'], 'dosage_form' => $snapshot['dosage_form'], 'route' => $snapshot['route'],
                    'registration_number' => $snapshot['registration_number'], 'instructions' => $canReadInstructions ? $snapshot['instructions'] : null,
                    'unit' => $line->unit, 'unit_price_minor' => (int) $line->unit_price_minor, 'total_minor' => (int) $line->total_minor,
                    'allocations' => DB::table('ph_dispensing_allocations')->where('dispensing_line_id', $line->id)->orderBy('id')->get()->map(function ($a): array {
                        $batch = DB::table('inv_batches')->find($a->batch_id);

                        return ['id' => $a->public_id, 'batch_id' => $a->batch_id, 'batch_code' => $batch?->batch_code, 'expires_at' => $batch?->expires_at, 'quantity' => (string) $a->quantity, 'status' => $a->status];
                    })->all()];
            })->all()];
    }

    public function lines(object $d): Collection
    {
        return DB::table('ph_dispensing_lines')->where('dispensing_id', $d->id)->where('status', 'active')->orderBy('id')->get();
    }

    private function writeLines(object $d, object $p, array $inputs): void
    {
        foreach ($inputs as $input) {
            validator($input, ['product_id' => 'required|string', 'quantity' => 'required', 'unit' => 'nullable|string|max:50', 'prescription_line_id' => 'nullable|string', 'packaging_id' => 'nullable|integer|min:1', 'instructions' => 'nullable|string|max:4000'])->validate();
            $product = $this->product($p, $input['product_id'], true);
            abort_if($product->requires_prescription && ! $d->prescription_id, 422, 'Thuốc kê đơn không được bán qua luồng OTC.');
            $saleQuantity = $this->quantity($input['quantity']);
            $quantity = $saleQuantity;
            $packaging = null;
            if (! empty($input['packaging_id'])) {
                $packaging = DB::table('merch_packagings')->where('id', $input['packaging_id'])->where('sku_id', $product->sku_id)->sharedLock()->first();
                abort_unless($packaging && $packaging->is_active, 422, 'Quy cách không thuộc thuốc.');
                $quantity = $this->quantity((string) BigDecimal::of($quantity)->multipliedBy((string) $packaging->quantity_multiplier)->strippedOfTrailingZeros());
            }
            $this->increment($product, $quantity);
            abort_if(isset($input['unit']) && $input['unit'] !== $product->base_unit && ! $packaging, 422, 'Đơn vị thuốc không khớp.');
            $price = DB::table('ph_prices')->where('pharmacy_id', $p->id)->where('product_id', $product->id)->where('status', 'active')->when($packaging, fn ($q) => $q->where('packaging_id', $packaging->id), fn ($q) => $q->whereNull('packaging_id'))->orderByDesc('id')->lockForUpdate()->first();
            abort_unless($price, 422, 'Thuốc chưa có giá niêm yết theo quy cách này.');
            $rxLine = null;
            if ($d->prescription_id) {
                $query = DB::table('ph_prescription_lines')->where('prescription_id', $d->prescription_id)->where('product_id', $product->id);
                if (! empty($input['prescription_line_id'])) {
                    $query->where('public_id', $input['prescription_line_id']);
                } else {
                    abort_unless((clone $query)->count() === 1, 422, 'Phải chọn đúng dòng đơn; không gộp các dòng cùng thuốc.');
                }
                $rxLine = $query->first();
                abort_unless($rxLine, 422, 'Thuốc không khớp dòng đơn. Thay thế thuốc chưa được mở.');
                abort_if(BigDecimal::of($quantity)->isGreaterThan((string) $rxLine->quantity_allowed), 422, 'Lượng cấp vượt dòng đơn.');
            }
            $snapshot = ['name' => $product->name, 'registration_number' => $product->registration_number, 'strength' => $product->strength, 'dosage_form' => $product->dosage_form, 'route' => $product->route, 'base_unit' => $product->base_unit,
                'price_id' => $price->id, 'price_version' => $price->version, 'product_version' => $product->version,
                'packaging_name' => $packaging?->name, 'packaging_code' => $packaging?->code, 'packaging_revision' => $packaging?->revision,
                'quantity_multiplier' => (string) BigDecimal::of((string) ($packaging?->quantity_multiplier ?? '1'))->toScale(6), 'sale_unit' => $packaging?->name ?? $product->base_unit,
                'instructions' => $rxLine ? $this->decrypt($rxLine->encrypted_instructions) : ($input['instructions'] ?? '')];
            $this->insert('ph_dispensing_lines', ['dispensing_id' => $d->id, 'product_id' => $product->id, 'prescription_line_id' => $rxLine?->id, 'packaging_id' => $packaging?->id,
                'quantity' => $quantity, 'sale_quantity' => $saleQuantity, 'unit' => $product->base_unit, 'unit_price_minor' => $price->price_minor, 'total_minor' => $this->amount($saleQuantity, (int) $price->price_minor), 'encrypted_snapshot' => $this->encrypt($snapshot)]);
        }
        abort_if((int) $this->lines($d)->sum('total_minor') > 99999999999999, 422, 'Tổng đơn vượt giới hạn tiền được hỗ trợ.');
    }

    private function productById(object $p, int $id, bool $lock = false): object
    {
        $publicId = DB::table('ph_products')->where('id', $id)->value('public_id');
        abort_unless($publicId, 404);

        return $this->product($p, $publicId, $lock);
    }

    private function validPrescription(Admin $actor, object $d, object $p): void
    {
        if (! $d->prescription_id) {
            return;
        }
        $id = DB::table('ph_prescriptions')->where('id', $d->prescription_id)->value('public_id');
        $rx = $id ? app(PrescriptionService::class)->accessible($actor, $p, $id, true) : null;
        abort_unless($rx && $rx->status === 'verified' && $rx->patient_id === $d->patient_id, 409, 'Đơn chưa được xác minh hoặc sai người bệnh.');
        abort_if(CarbonImmutable::parse($rx->expires_at, 'UTC')->isPast(), 409, 'Đơn đã hết hiệu lực.');
    }

    private function eligible(Admin $actor, object $d, object $p, bool $checkReservation = true, string $action = 'dispensing.prepare', ?Admin $clinicalActor = null): void
    {
        if ($d->patient_id) {
            $patient = DB::table('ph_patients')->where('id', $d->patient_id)->sharedLock()->first();
            abort_unless($patient && $patient->status === 'active', 409, 'Hồ sơ người bệnh không còn hoạt động.');
        }
        $this->validPrescription($clinicalActor ?? $actor, $d, $p);
        if ($checkReservation) {
            abort_if(! $d->reservation_expires_at || CarbonImmutable::parse($d->reservation_expires_at, 'UTC')->isPast(), 409, 'Giữ hàng/giữ lượng đã hết hạn; hủy và chuẩn bị lại.');
        }
        foreach ($this->lines($d) as $line) {
            $product = $this->productById($p, $line->product_id, true);
            if ($checkReservation) {
                $this->assertPackaging($line, $product);
            }
            abort_if($product->requires_prescription && ! $line->prescription_line_id, 409, 'Thuốc đã chuyển sang kê đơn; cần tiếp nhận đơn.');
            foreach (DB::table('ph_dispensing_allocations')->where('dispensing_line_id', $line->id)->whereIn('status', ['reserved', 'committed'])->get() as $allocation) {
                app(PharmacyInventoryService::class)->assertEligible($actor, $p, $product, (int) $allocation->batch_id, $action);
            }
            if ($line->prescription_line_id && $checkReservation) {
                abort_unless(DB::table('ph_dispense_claims')->where('dispensing_line_id', $line->id)->where('status', 'reserved')->where('expires_at', '>', now())->exists(), 409, 'Giữ lượng trên đơn không còn hiệu lực.');
            }
        }
    }

    private function fingerprint(object $d, object $p): string
    {
        $parts = [$d->public_id, $d->patient_id ? DB::table('ph_patients')->where('id', $d->patient_id)->value('version') : null, $d->prescription_id ? DB::table('ph_prescriptions')->where('id', $d->prescription_id)->value('version') : null];
        foreach ($this->lines($d) as $line) {
            $product = $this->productById($p, $line->product_id);
            $snapshot = $this->decrypt($line->encrypted_snapshot);
            abort_unless((int) $snapshot['product_version'] === (int) $product->version, 409, 'Thông tin thuốc đã đổi; cập nhật dòng và chuẩn bị lại.');
            $price = DB::table('ph_prices')->find($snapshot['price_id']);
            abort_unless($price && $price->status === 'active' && (int) $price->version === (int) $snapshot['price_version'] && (int) $price->price_minor === (int) $line->unit_price_minor, 409, 'Giá đã đổi; cập nhật dòng và chuẩn bị lại.');
            $parts[] = [$line->public_id, $line->quantity, $line->total_minor, $line->encrypted_snapshot, $product->version,
                DB::table('ph_dispensing_allocations')->where('dispensing_line_id', $line->id)->whereIn('status', ['reserved', 'committed'])->orderBy('id')->get(['batch_id', 'quantity'])->toArray()];
        }

        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    private function assertPackaging(object $line, object $product): void
    {
        if (! $line->packaging_id) {
            return;
        }
        $pack = DB::table('merch_packagings')->where('id', $line->packaging_id)->where('sku_id', $product->sku_id)->sharedLock()->first();
        abort_unless($pack && $pack->is_active && BigDecimal::of((string) $line->sale_quantity)->multipliedBy((string) $pack->quantity_multiplier)->isEqualTo((string) $line->quantity), 409, 'Quy cách đã thay đổi/ngừng dùng; cập nhật dòng, chuẩn bị và duyệt lại.');
        $snapshot = $this->decrypt($line->encrypted_snapshot);
        abort_if(isset($snapshot['packaging_revision']) && (int) $snapshot['packaging_revision'] !== (int) $pack->revision, 409, 'Phiên bản quy cách đã thay đổi.');
    }

    private function release(Admin $actor, object $d, object $p): void
    {
        foreach (DB::table('ph_dispensing_allocations')->whereIn('dispensing_line_id', $this->lines($d)->pluck('id'))->where('status', 'reserved')->orderBy('id')->get() as $a) {
            app(PharmacyInventoryService::class)->release($actor, $p, $a->reservation_id, 'ph:release:'.$a->public_id);
            DB::table('ph_dispensing_allocations')->where('id', $a->id)->update(['status' => 'released', 'updated_at' => now()]);
        }
        DB::table('ph_dispense_claims')->whereIn('dispensing_line_id', $this->lines($d)->pluck('id'))->whereIn('status', ['reserved', 'expired'])->update(['status' => 'released', 'updated_at' => now()]);
    }
}
