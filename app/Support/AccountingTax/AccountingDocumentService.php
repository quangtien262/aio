<?php

namespace App\Support\AccountingTax;

use App\Models\AcctDocument;
use App\Models\AcctDocumentEvent;
use App\Models\AcctDocumentLine;
use App\Models\AcctDocumentPayment;
use App\Models\AcctItem;
use App\Models\AcctOrganization;
use App\Models\AcctParty;
use App\Models\AcctTaxPeriod;
use App\Support\AuditLogger;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingDocumentService
{
    public const TAX_CATEGORIES = ['standard', 'zero_rated', 'not_subject', 'not_declared', 'exempt'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccountingDocumentNumberService $documentNumbers,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(array $payload, ?int $adminId = null): AcctDocument
    {
        $organizationId = (int) ($payload['organization_id'] ?? 0);
        $fingerprint = $this->fingerprint($payload);

        return DB::transaction(function () use ($payload, $organizationId, $fingerprint, $adminId): AcctDocument {
            if ($organizationId < 1) {
                throw ValidationException::withMessages(['organization_id' => ['Pháp nhân là bắt buộc.']]);
            }

            if ($existing = $this->existingIdempotentDocument($organizationId, $payload['idempotency_key'] ?? null)) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Khóa idempotency đã được dùng cho một nội dung khác.'],
                    ]);
                }

                return $existing->load(['party', 'lines', 'payments']);
            }

            $organization = AcctOrganization::query()->lockForUpdate()->findOrFail($organizationId);
            $this->assertClientCannotGrantTaxEligibility($payload);
            $correction = $this->resolveCorrection($organizationId, $payload);
            $party = $this->resolveParty($organizationId, $payload['party_id'] ?? null);
            $this->assertWebsiteBelongsToOrganization($organization, $payload['website_key'] ?? null);
            $direction = (string) $payload['direction'];
            [$sellerSnapshot, $buyerSnapshot] = $this->legalPartySnapshots($organization, $party, $direction);
            $currency = strtoupper((string) ($payload['currency'] ?? $organization->default_currency));
            $baseCurrency = strtoupper((string) $organization->default_currency);
            $exchangeRate = $currency === $baseCurrency
                ? '1.00000000'
                : $this->decimal($payload['exchange_rate'] ?? null, 8, 'exchange_rate')->__toString();

            if (BigDecimal::of($exchangeRate)->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['exchange_rate' => ['Tỷ giá phải lớn hơn 0.']]);
            }

            /** @var AcctDocument $document */
            $document = AcctDocument::query()->create([
                ...Arr::only($payload, [
                    'party_id', 'direction', 'document_type', 'document_no', 'document_date', 'due_date',
                    'website_key', 'source_module', 'source_type', 'source_id',
                    'idempotency_key', 'notes', 'metadata', 'tax_period', 'tax_eligibility',
                ]),
                'organization_id' => $organizationId,
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'exchange_rate' => $exchangeRate,
                'workflow_status' => 'draft',
                'payment_status' => 'unpaid',
                'seller_snapshot' => $sellerSnapshot,
                'buyer_snapshot' => $buyerSnapshot,
                'created_by' => $adminId,
                'version' => 1,
                'request_fingerprint' => $fingerprint,
                'original_document_id' => $correction['original_document_id'],
                'correction_type' => $correction['correction_type'],
                'effect_sign' => $correction['effect_sign'],
                'subtotal' => '0.00',
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'grand_total' => '0.00',
                'base_subtotal' => '0.00',
                'base_discount_total' => '0.00',
                'base_tax_total' => '0.00',
                'base_grand_total' => '0.00',
                'paid_amount' => '0.00',
            ]);

            foreach ($payload['lines'] ?? [] as $index => $line) {
                $this->createLine($document, $line, $index);
            }

            $document = $this->recalculate($document);
            $this->recordEvent($document, 'created', null, 'draft', $adminId, $payload['idempotency_key'] ?? null);
            $this->audit->record('accounting.document.created', $document, null, $this->auditSnapshot($document), moduleKey: 'accounting-tax');

            return $document->load(['party', 'lines', 'payments']);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateDraft(AcctDocument $document, array $payload, ?int $adminId, ?int $expectedVersion = null): AcctDocument
    {
        return DB::transaction(function () use ($document, $payload, $adminId, $expectedVersion): AcctDocument {
            $locked = $this->locked($document);
            $this->assertVersion($locked, $expectedVersion);
            $this->assertPeriodOpen((int) $locked->organization_id, $locked->document_date?->toDateString());
            $this->assertPeriodOpen(
                (int) $locked->organization_id,
                isset($payload['document_date']) ? (string) $payload['document_date'] : null,
            );
            $this->assertClientCannotGrantTaxEligibility($payload);

            if ($locked->workflow_status !== 'draft') {
                throw ValidationException::withMessages(['document' => ['Chỉ chứng từ nháp mới được chỉnh sửa.']]);
            }

            $before = $this->auditSnapshot($locked->load('lines'));
            $organization = AcctOrganization::query()->findOrFail($locked->organization_id);
            $nextDocumentType = (string) ($payload['document_type'] ?? $locked->document_type);
            if ($nextDocumentType !== $locked->document_type
                && (in_array($nextDocumentType, ['credit_note', 'debit_note'], true)
                    || in_array($locked->document_type, ['credit_note', 'debit_note'], true))) {
                throw ValidationException::withMessages([
                    'document_type' => ['Không đổi trực tiếp sang hoặc khỏi chứng từ điều chỉnh; hãy tạo chứng từ có liên kết với bản gốc.'],
                ]);
            }
            $partyId = array_key_exists('party_id', $payload) ? $payload['party_id'] : $locked->party_id;
            $party = $this->resolveParty((int) $locked->organization_id, $partyId);
            $websiteKey = array_key_exists('website_key', $payload) ? $payload['website_key'] : $locked->website_key;
            $this->assertWebsiteBelongsToOrganization($organization, $websiteKey);
            [$sellerSnapshot, $buyerSnapshot] = $this->legalPartySnapshots(
                $organization,
                $party,
                (string) ($payload['direction'] ?? $locked->direction),
            );
            $currency = strtoupper((string) ($payload['currency'] ?? $locked->currency));
            $baseCurrency = strtoupper((string) $organization->default_currency);
            $exchangeRate = $currency === $baseCurrency
                ? '1.00000000'
                : $this->decimal(
                    $payload['exchange_rate'] ?? ($currency === $locked->currency ? $locked->exchange_rate : null),
                    8,
                    'exchange_rate',
                )->__toString();

            if (BigDecimal::of($exchangeRate)->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['exchange_rate' => ['Tỷ giá phải lớn hơn 0.']]);
            }

            $locked->forceFill([
                ...Arr::only($payload, [
                    'party_id', 'direction', 'document_type', 'document_no', 'document_date', 'due_date',
                    'website_key', 'notes', 'metadata', 'tax_period', 'tax_eligibility',
                ]),
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'exchange_rate' => $exchangeRate,
                'seller_snapshot' => $sellerSnapshot,
                'buyer_snapshot' => $buyerSnapshot,
                'version' => $locked->version + 1,
            ])->save();

            if (array_key_exists('lines', $payload)) {
                $locked->lines()->delete();

                foreach ($payload['lines'] as $index => $line) {
                    $this->createLine($locked, $line, $index);
                }
            }

            $locked = $this->recalculate($locked);
            $this->recordEvent($locked, 'updated', 'draft', 'draft', $adminId, $payload['idempotency_key'] ?? null);
            $this->audit->record('accounting.document.updated', $locked, $before, $this->auditSnapshot($locked), moduleKey: 'accounting-tax');

            return $locked->load(['party', 'lines', 'payments']);
        }, 3);
    }

    public function approve(AcctDocument $document, ?int $adminId, ?int $expectedVersion = null, ?string $idempotencyKey = null): AcctDocument
    {
        return $this->transition($document, 'approved', $adminId, $expectedVersion, $idempotencyKey, function (AcctDocument $locked) use ($adminId): array {
            if ($locked->workflow_status !== 'draft') {
                throw ValidationException::withMessages(['document' => ['Chỉ chứng từ nháp mới được duyệt.']]);
            }

            if ($adminId === null || $locked->created_by === null) {
                throw ValidationException::withMessages(['document' => ['Chứng từ phải có đủ thông tin người lập và người duyệt.']]);
            }

            if ((int) $locked->created_by === $adminId) {
                throw ValidationException::withMessages(['document' => ['Người duyệt phải khác người lập chứng từ.']]);
            }

            if (trim((string) $locked->document_no) === '' && $locked->document_date !== null) {
                $locked->document_no = $this->documentNumbers->next(
                    (int) $locked->organization_id,
                    (string) $locked->document_type,
                    $locked->document_date,
                );
            }

            $this->assertReadyForApproval($locked);

            return [
                'document_no' => $locked->document_no,
                'snapshot_hash' => $this->snapshotHash($locked),
                'workflow_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $adminId,
            ];
        });
    }

    public function post(AcctDocument $document, ?int $adminId, ?int $expectedVersion = null, ?string $idempotencyKey = null): AcctDocument
    {
        return $this->transition($document, 'posted', $adminId, $expectedVersion, $idempotencyKey, function (AcctDocument $locked) use ($adminId): array {
            if ($locked->workflow_status !== 'approved') {
                throw ValidationException::withMessages(['document' => ['Chỉ chứng từ đã duyệt mới được ghi sổ.']]);
            }

            if ($adminId === null) {
                throw ValidationException::withMessages(['document' => ['Không xác định được người ghi sổ.']]);
            }

            $this->assertPeriodOpen((int) $locked->organization_id, $locked->document_date?->toDateString());
            $this->assertReadyForApproval($locked);

            return ['workflow_status' => 'posted', 'posted_at' => now(), 'posted_by' => $adminId];
        }, function (AcctDocument $posted) use ($adminId): void {
            if ($posted->correction_type === 'reversal' && $posted->original_document_id !== null) {
                $original = AcctDocument::query()->lockForUpdate()->findOrFail($posted->original_document_id);
                $original->forceFill([
                    'reversal_status' => 'reversed',
                    'reversed_at' => now(),
                    'reversed_by' => $adminId,
                    'version' => $original->version + 1,
                ])->saveTrusted();
            }
        });
    }

    public function void(AcctDocument $document, ?int $adminId, string $reason, ?int $expectedVersion = null, ?string $idempotencyKey = null): AcctDocument
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => ['Lý do vô hiệu chứng từ là bắt buộc.']]);
        }

        return $this->transition($document, 'voided', $adminId, $expectedVersion, $idempotencyKey, function (AcctDocument $locked) use ($adminId, $reason): array {
            if (! in_array($locked->workflow_status, ['draft', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'document' => ['Chứng từ đã ghi sổ phải được lập chứng từ đảo, không được vô hiệu trực tiếp.'],
                ]);
            }

            $this->assertPeriodOpen((int) $locked->organization_id, $locked->document_date?->toDateString());

            return [
                'workflow_status' => 'voided',
                'voided_at' => now(),
                'voided_by' => $adminId,
                'void_reason' => $reason,
            ];
        });
    }

    public function createReversal(
        AcctDocument $document,
        ?int $adminId,
        string $reason,
        ?string $documentNo = null,
        ?int $expectedVersion = null,
        ?string $idempotencyKey = null,
        ?string $reversalDate = null,
    ): AcctDocument {
        return DB::transaction(function () use ($document, $adminId, $reason, $documentNo, $expectedVersion, $idempotencyKey, $reversalDate): AcctDocument {
            $original = $this->locked($document)->load('lines');
            $this->assertVersion($original, $expectedVersion);

            if ($original->workflow_status !== 'posted' || $original->reversal_status !== 'none') {
                throw ValidationException::withMessages(['document' => ['Chứng từ không còn ở trạng thái có thể lập đảo.']]);
            }

            if ($adminId === null || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => ['Người lập và lý do đảo chứng từ là bắt buộc.']]);
            }

            if ($this->periodIsLocked((int) $original->organization_id, $original->document_date?->toDateString())
                && ($reversalDate === null || trim($reversalDate) === '')) {
                throw ValidationException::withMessages([
                    'document_date' => ['Kỳ gốc đã khóa; phải chọn ngày chứng từ đảo thuộc một kỳ đang mở.'],
                ]);
            }

            $effectiveReversalDate = $reversalDate ?: now()->toDateString();
            $this->assertPeriodOpen((int) $original->organization_id, $effectiveReversalDate);

            if ($idempotencyKey !== null && $idempotent = AcctDocument::query()
                ->where('organization_id', $original->organization_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first()) {
                return $idempotent->load(['party', 'lines', 'payments']);
            }

            /** @var AcctDocument $reversal */
            $reversal = AcctDocument::query()->create([
                ...Arr::only($original->getAttributes(), [
                    'organization_id', 'party_id', 'direction', 'currency', 'base_currency', 'exchange_rate',
                    'website_key', 'seller_snapshot', 'buyer_snapshot', 'tax_eligibility',
                ]),
                'document_type' => 'credit_note',
                'document_no' => $documentNo,
                'document_date' => $effectiveReversalDate,
                'workflow_status' => 'draft',
                'payment_status' => 'unpaid',
                'legal_status' => 'not_due',
                'inventory_status' => 'not_applicable',
                'mail_status' => 'not_sent',
                'subtotal' => $original->subtotal,
                'discount_total' => $original->discount_total,
                'tax_total' => $original->tax_total,
                'grand_total' => $original->grand_total,
                'base_subtotal' => $original->base_subtotal,
                'base_discount_total' => $original->base_discount_total,
                'base_tax_total' => $original->base_tax_total,
                'base_grand_total' => $original->base_grand_total,
                'paid_amount' => '0.00',
                'tax_breakdown' => $original->tax_breakdown,
                'original_document_id' => $original->id,
                'correction_type' => 'reversal',
                'effect_sign' => -1,
                'reversal_status' => 'none',
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => hash('sha256', $original->id.'|'.$reason.'|'.$documentNo),
                'notes' => trim($reason),
                'metadata' => ['reversal_reason' => trim($reason)],
                'created_by' => $adminId,
                'version' => 1,
            ]);

            foreach ($original->lines as $line) {
                AcctDocumentLine::query()->create([
                    ...Arr::except($line->getAttributes(), ['id', 'document_id', 'created_at', 'updated_at']),
                    'document_id' => $reversal->id,
                ]);
            }

            $reversal->forceFill(['snapshot_hash' => $this->snapshotHash($reversal->load('lines'))])->save();
            $original->forceFill(['reversal_status' => 'pending', 'version' => $original->version + 1])->saveTrusted();
            $this->recordEvent($reversal, 'created', null, 'draft', $adminId, $idempotencyKey, ['original_document_id' => $original->id]);
            $this->recordEvent($original, 'reversal_requested', 'posted', 'posted', $adminId, $idempotencyKey, ['reversal_document_id' => $reversal->id]);
            $this->audit->record('accounting.document.reversal_created', $reversal, null, $this->auditSnapshot($reversal), moduleKey: 'accounting-tax');

            return $reversal->load(['party', 'lines', 'payments']);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function recordPayment(AcctDocument $document, array $payload, ?int $adminId, ?int $expectedVersion = null): AcctDocumentPayment
    {
        return DB::transaction(function () use ($document, $payload, $adminId, $expectedVersion): AcctDocumentPayment {
            $locked = $this->locked($document);
            $this->assertVersion($locked, $expectedVersion);
            $this->assertPeriodOpen((int) $locked->organization_id, $locked->document_date?->toDateString());

            if ($locked->workflow_status !== 'posted') {
                throw ValidationException::withMessages(['document' => ['Chỉ ghi nhận thanh toán cho chứng từ đã ghi sổ.']]);
            }

            $idempotencyKey = $payload['idempotency_key'] ?? null;

            if ($idempotencyKey !== null && $existing = AcctDocumentPayment::query()
                ->where('document_id', $locked->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first()) {
                return $existing;
            }

            $amount = $this->decimal($payload['amount'] ?? null, 2, 'amount');

            if ($amount->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['amount' => ['Số tiền thanh toán phải lớn hơn 0.']]);
            }

            $kind = (string) ($payload['kind'] ?? 'payment');
            $currentPaid = BigDecimal::of((string) $locked->paid_amount);
            $newPaid = $kind === 'refund' ? $currentPaid->minus($amount) : $currentPaid->plus($amount);
            $grandTotal = BigDecimal::of((string) $locked->grand_total);

            if ($newPaid->isLessThan(BigDecimal::zero()) || $newPaid->isGreaterThan($grandTotal)) {
                throw ValidationException::withMessages(['amount' => ['Số tiền lũy kế phải nằm trong giá trị chứng từ.']]);
            }

            /** @var AcctDocumentPayment $payment */
            $payment = AcctDocumentPayment::query()->create([
                'document_id' => $locked->id,
                'kind' => $kind,
                'amount' => $amount->__toString(),
                'currency' => $locked->currency,
                'paid_at' => $payload['paid_at'] ?? now(),
                'reference' => $payload['reference'] ?? null,
                'status' => 'recorded',
                'created_by' => $adminId,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $paymentStatus = $newPaid->isZero() ? 'unpaid' : ($newPaid->isEqualTo($grandTotal) ? 'paid' : 'partial');
            $locked->forceFill([
                'paid_amount' => $newPaid->toScale(2, RoundingMode::HalfUp)->__toString(),
                'payment_status' => $paymentStatus,
                'version' => $locked->version + 1,
            ])->saveTrusted();

            $eventPayload = [
                'payment_id' => $payment->id,
                'kind' => $kind,
                'amount' => $amount->__toString(),
                'payment_status' => $paymentStatus,
            ];
            $this->recordEvent($locked, 'payment_recorded', 'posted', 'posted', $adminId, $idempotencyKey, $eventPayload);
            $this->audit->record('accounting.document.payment_recorded', $locked, null, $eventPayload, moduleKey: 'accounting-tax');

            return $payment;
        }, 3);
    }

    public function assessTaxEligibility(
        AcctDocument $document,
        string $eligibility,
        string $reason,
        ?int $adminId,
        ?int $expectedVersion = null,
        ?string $idempotencyKey = null,
    ): AcctDocument {
        if (! in_array($eligibility, ['eligible', 'ineligible'], true) || trim($reason) === '') {
            throw ValidationException::withMessages([
                'tax_eligibility' => ['Kết quả và lý do đánh giá thuế là bắt buộc.'],
            ]);
        }

        return DB::transaction(function () use ($document, $eligibility, $reason, $adminId, $expectedVersion, $idempotencyKey): AcctDocument {
            $locked = $this->locked($document);

            if ($idempotencyKey !== null && AcctDocumentEvent::query()
                ->where('document_id', $locked->id)
                ->where('event_type', 'tax_eligibility_assessed')
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                return $locked->load(['party', 'lines', 'payments']);
            }

            $this->assertVersion($locked, $expectedVersion);
            $this->assertPeriodOpen((int) $locked->organization_id, $locked->document_date?->toDateString());

            if ($locked->workflow_status !== 'posted' || $locked->direction !== 'inbound') {
                throw ValidationException::withMessages([
                    'document' => ['Chỉ hóa đơn đầu vào đã ghi sổ mới được đánh giá khấu trừ thuế.'],
                ]);
            }

            if ($eligibility === 'eligible') {
                $legalStatuses = ['accepted', 'validated', 'valid', 'issued'];
                $sellerTaxCode = (string) data_get($locked->seller_snapshot, 'tax_code', '');

                if (! in_array($locked->document_type, ['tax_invoice', 'credit_note', 'debit_note'], true)
                    || ! in_array($locked->legal_status, $legalStatuses, true)
                    || BigDecimal::of((string) $locked->tax_total)->isLessThanOrEqualTo(BigDecimal::zero())
                    || trim($sellerTaxCode) === '') {
                    throw ValidationException::withMessages([
                        'tax_eligibility' => ['Hóa đơn chưa đủ bằng chứng pháp lý để đánh dấu đủ điều kiện khấu trừ.'],
                    ]);
                }
            }

            $before = $this->auditSnapshot($locked);
            $locked->forceFill([
                'tax_eligibility' => $eligibility,
                'version' => $locked->version + 1,
                'metadata' => [
                    ...($locked->metadata ?? []),
                    'tax_eligibility_assessment' => [
                        'result' => $eligibility,
                        'reason' => trim($reason),
                        'assessed_by' => $adminId,
                        'assessed_at' => now()->toIso8601String(),
                    ],
                ],
            ])->saveTrusted();
            $this->recordEvent(
                $locked,
                'tax_eligibility_assessed',
                'posted',
                'posted',
                $adminId,
                $idempotencyKey,
                ['result' => $eligibility, 'reason' => trim($reason)],
            );
            $this->audit->record(
                'accounting.document.tax_eligibility_assessed',
                $locked,
                $before,
                $this->auditSnapshot($locked),
                moduleKey: 'accounting-tax',
            );

            return $locked->fresh(['party', 'lines', 'payments']);
        }, 3);
    }

    /** @param array<string, mixed> $line */
    private function createLine(AcctDocument $document, array $line, int $index): AcctDocumentLine
    {
        $lineType = (string) ($line['line_type'] ?? 'item');
        $item = isset($line['accounting_item_id'])
            ? AcctItem::query()->with('sources')->findOrFail((int) $line['accounting_item_id'])
            : null;

        if ($item !== null && (int) $item->organization_id !== (int) $document->organization_id) {
            throw ValidationException::withMessages([
                "lines.{$index}.accounting_item_id" => ['Sản phẩm/dịch vụ không thuộc pháp nhân của chứng từ.'],
            ]);
        }

        $quantity = $this->decimal($line['quantity'] ?? 1, 4, "lines.{$index}.quantity");
        $unitPrice = $this->decimal($line['unit_price'] ?? $item?->default_price ?? 0, 2, "lines.{$index}.unit_price");
        $discount = $this->decimal($line['discount_amount'] ?? 0, 2, "lines.{$index}.discount_amount");
        $taxCategory = $this->normalizeTaxCategory((string) ($line['tax_category'] ?? $item?->tax_category ?? 'standard'));
        $taxRate = $taxCategory === 'standard'
            ? $this->decimal($line['tax_rate'] ?? $item?->tax_rate ?? 0, 2, "lines.{$index}.tax_rate")
            : BigDecimal::zero()->toScale(2);

        if ($quantity->isLessThan(BigDecimal::zero()) || $unitPrice->isLessThan(BigDecimal::zero()) || $discount->isLessThan(BigDecimal::zero())) {
            throw ValidationException::withMessages(["lines.{$index}" => ['Số lượng, đơn giá và chiết khấu không được âm.']]);
        }

        $lineSubtotal = $lineType === 'note'
            ? BigDecimal::zero()->toScale(2)
            : $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);

        if ($lineType === 'discount') {
            $discount = $discount->isZero() ? $lineSubtotal : $discount;
            $lineSubtotal = BigDecimal::zero()->toScale(2);
        } elseif ($discount->isGreaterThan($lineSubtotal)) {
            throw ValidationException::withMessages([
                "lines.{$index}.discount_amount" => ['Chiết khấu dòng không được lớn hơn tiền hàng của dòng.'],
            ]);
        }

        $taxBase = in_array($lineType, ['discount', 'note'], true)
            ? BigDecimal::zero()->toScale(2)
            : $lineSubtotal->minus($discount)->toScale(2, RoundingMode::HalfUp);
        $taxAmount = $taxCategory === 'standard'
            ? $taxBase->multipliedBy($taxRate)->dividedBy(100, 2, RoundingMode::HalfUp)
            : BigDecimal::zero()->toScale(2);
        $lineTotal = $lineType === 'discount'
            ? $discount->negated()
            : $taxBase->plus($taxAmount)->toScale(2, RoundingMode::HalfUp);

        $legalSnapshot = $item === null ? [
            'origin' => 'manual',
            'name' => (string) ($line['name'] ?? ''),
            'sku' => $line['sku'] ?? null,
            'unit' => $line['unit'] ?? null,
            'item_kind' => $line['item_kind'] ?? null,
        ] : [
            'origin' => 'accounting_item',
            'accounting_item_id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'item_kind' => $item->kind,
            'revenue_account' => $item->revenue_account,
            'expense_account' => $item->expense_account,
            'source_mappings' => $item->sources->map(fn ($source): array => [
                'module' => $source->source_module,
                'type' => $source->source_type,
                'id' => $source->source_id,
                'hash' => $source->source_hash,
            ])->values()->all(),
        ];

        if (isset($line['snapshot']) && is_array($line['snapshot'])) {
            $legalSnapshot['client_metadata'] = $line['snapshot'];
        }

        return AcctDocumentLine::query()->create([
            'document_id' => $document->id,
            'accounting_item_id' => $item?->id,
            'line_type' => $lineType,
            'sort_order' => $line['sort_order'] ?? $index,
            'item_kind' => $item?->kind ?? ($line['item_kind'] ?? null),
            'name' => $item?->name ?? (string) ($line['name'] ?? ''),
            'sku' => $item?->sku ?? ($line['sku'] ?? null),
            'unit' => $item?->unit ?? ($line['unit'] ?? null),
            'quantity' => $quantity->__toString(),
            'unit_price' => $unitPrice->__toString(),
            'line_subtotal' => $lineSubtotal->__toString(),
            'discount_amount' => $discount->__toString(),
            'tax_category' => $taxCategory,
            'tax_rate' => $taxRate->__toString(),
            'tax_base' => $taxBase->__toString(),
            'tax_amount' => $taxAmount->__toString(),
            'line_total' => $lineTotal->__toString(),
            'snapshot' => $legalSnapshot,
        ]);
    }

    private function recalculate(AcctDocument $document): AcctDocument
    {
        $lines = $document->lines()->get();
        $subtotal = BigDecimal::zero();
        $discount = BigDecimal::zero();
        $tax = BigDecimal::zero();
        $breakdown = [];

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus((string) $line->line_subtotal);
            $discount = $discount->plus((string) $line->discount_amount);
            $tax = $tax->plus((string) $line->tax_amount);
            $key = $line->tax_category.':'.($line->tax_rate ?? '0.00');
            $breakdown[$key] ??= [
                'tax_category' => $line->tax_category,
                'tax_rate' => (string) ($line->tax_rate ?? '0.00'),
                'tax_base' => '0.00',
                'tax_amount' => '0.00',
            ];
            $breakdown[$key]['tax_base'] = BigDecimal::of($breakdown[$key]['tax_base'])
                ->plus((string) $line->tax_base)->toScale(2, RoundingMode::HalfUp)->__toString();
            $breakdown[$key]['tax_amount'] = BigDecimal::of($breakdown[$key]['tax_amount'])
                ->plus((string) $line->tax_amount)->toScale(2, RoundingMode::HalfUp)->__toString();
        }

        if ($discount->isGreaterThan($subtotal)) {
            throw ValidationException::withMessages(['lines' => ['Tổng chiết khấu không được lớn hơn tổng tiền hàng.']]);
        }

        $grandTotal = $subtotal->minus($discount)->plus($tax)->toScale(2, RoundingMode::HalfUp);
        $rate = BigDecimal::of((string) $document->exchange_rate);
        $toBase = fn (BigDecimal $amount): string => $amount->multipliedBy($rate)
            ->toScale(2, RoundingMode::HalfUp)->__toString();

        $document->forceFill([
            'subtotal' => $subtotal->toScale(2, RoundingMode::HalfUp)->__toString(),
            'discount_total' => $discount->toScale(2, RoundingMode::HalfUp)->__toString(),
            'tax_total' => $tax->toScale(2, RoundingMode::HalfUp)->__toString(),
            'grand_total' => $grandTotal->__toString(),
            'base_subtotal' => $toBase($subtotal),
            'base_discount_total' => $toBase($discount),
            'base_tax_total' => $toBase($tax),
            'base_grand_total' => $toBase($grandTotal),
            'tax_breakdown' => array_values($breakdown),
        ])->save();
        $document->forceFill(['snapshot_hash' => $this->snapshotHash($document->load('lines'))])->save();

        return $document->fresh(['lines']);
    }

    /** @param callable(AcctDocument):array<string, mixed> $changes */
    private function transition(
        AcctDocument $document,
        string $toStatus,
        ?int $adminId,
        ?int $expectedVersion,
        ?string $idempotencyKey,
        callable $changes,
        ?callable $after = null,
    ): AcctDocument {
        return DB::transaction(function () use ($document, $toStatus, $adminId, $expectedVersion, $idempotencyKey, $changes, $after): AcctDocument {
            $locked = $this->locked($document);

            if ($idempotencyKey !== null && AcctDocumentEvent::query()
                ->where('document_id', $locked->id)
                ->where('event_type', $toStatus)
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                return $locked->load(['party', 'lines', 'payments']);
            }

            $this->assertVersion($locked, $expectedVersion);
            $fromStatus = (string) $locked->workflow_status;
            $before = $this->auditSnapshot($locked);
            $locked->forceFill([...$changes($locked), 'version' => $locked->version + 1])->saveTrusted();

            if ($after !== null) {
                $after($locked);
            }

            $this->recordEvent($locked, $toStatus, $fromStatus, $toStatus, $adminId, $idempotencyKey);
            $this->audit->record("accounting.document.{$toStatus}", $locked, $before, $this->auditSnapshot($locked), moduleKey: 'accounting-tax');

            return $locked->fresh(['party', 'lines', 'payments']);
        }, 3);
    }

    private function locked(AcctDocument $document): AcctDocument
    {
        return AcctDocument::query()->lockForUpdate()->findOrFail($document->getKey());
    }

    private function assertVersion(AcctDocument $document, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $document->version !== $expectedVersion) {
            throw ValidationException::withMessages([
                'version' => ['Chứng từ đã thay đổi. Hãy tải lại dữ liệu trước khi thao tác.'],
            ]);
        }
    }

    private function assertReadyForApproval(AcctDocument $document): void
    {
        $document->loadMissing('lines');
        $errors = [];

        if (trim((string) $document->document_no) === '') {
            $errors['document_no'][] = 'Số chứng từ là bắt buộc trước khi duyệt.';
        }
        if ($document->document_date === null) {
            $errors['document_date'][] = 'Ngày chứng từ là bắt buộc trước khi duyệt.';
        }
        if ($document->party_id === null || $document->seller_snapshot === null || $document->buyer_snapshot === null) {
            $errors['party_id'][] = 'Đối tác và snapshot người bán/người mua là bắt buộc trước khi duyệt.';
        }
        if ($document->lines->where('line_type', '!=', 'note')->isEmpty()) {
            $errors['lines'][] = 'Cần ít nhất một dòng có giá trị trước khi duyệt.';
        }
        if ($document->lines->contains(
            fn (AcctDocumentLine $line): bool => data_get(
                $line->snapshot,
                'client_metadata.requires_tax_classification',
                false,
            ),
        )) {
            $errors['lines'][] = 'Cần rà soát và lưu lại phân loại thuế cho các dòng được tạo từ nguồn ngoài.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function resolveParty(int $organizationId, mixed $partyId): ?AcctParty
    {
        if ($partyId === null || $partyId === '') {
            return null;
        }

        $party = AcctParty::query()->findOrFail((int) $partyId);
        if ((int) $party->organization_id !== $organizationId) {
            throw ValidationException::withMessages(['party_id' => ['Đối tác không thuộc pháp nhân của chứng từ.']]);
        }

        return $party;
    }

    private function assertWebsiteBelongsToOrganization(AcctOrganization $organization, mixed $websiteKey): void
    {
        if ($websiteKey !== null && $websiteKey !== ''
            && ! $organization->websites()->where('website_key', (string) $websiteKey)->exists()) {
            throw ValidationException::withMessages([
                'website_key' => ['Website không được ánh xạ với pháp nhân của chứng từ.'],
            ]);
        }
    }

    /** @return array{0:array<string,mixed>|null,1:array<string,mixed>|null} */
    private function legalPartySnapshots(AcctOrganization $organization, ?AcctParty $party, string $direction): array
    {
        $organizationSnapshot = [
            'entity_type' => 'organization',
            'id' => $organization->id,
            'legal_name' => $organization->legal_name ?: $organization->name,
            'tax_code' => $organization->tax_code,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'address' => $organization->address,
        ];
        $partySnapshot = $party ? [
            'entity_type' => 'party',
            'id' => $party->id,
            'party_type' => $party->type,
            'legal_name' => $party->name,
            'tax_code' => $party->tax_code,
            'email' => $party->email,
            'phone' => $party->phone,
            'address' => $party->address,
        ] : null;

        return $direction === 'inbound' ? [$partySnapshot, $organizationSnapshot] : [$organizationSnapshot, $partySnapshot];
    }

    private function normalizeTaxCategory(string $category): string
    {
        $category = $category === 'vat' ? 'standard' : $category;
        if (! in_array($category, self::TAX_CATEGORIES, true)) {
            throw ValidationException::withMessages(['tax_category' => ['Nhóm thuế GTGT không hợp lệ.']]);
        }

        return $category;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{original_document_id:?int,correction_type:?string,effect_sign:int}
     */
    private function resolveCorrection(int $organizationId, array $payload): array
    {
        if (array_key_exists('effect_sign', $payload)) {
            throw ValidationException::withMessages([
                'effect_sign' => ['Dấu tác động được hệ thống xác định từ loại chứng từ.'],
            ]);
        }

        $documentType = (string) ($payload['document_type'] ?? 'internal_invoice');

        if (! in_array($documentType, ['credit_note', 'debit_note'], true)) {
            if (isset($payload['original_document_id']) || isset($payload['correction_type'])) {
                throw ValidationException::withMessages([
                    'original_document_id' => ['Chỉ chứng từ điều chỉnh mới được liên kết chứng từ gốc.'],
                ]);
            }

            return ['original_document_id' => null, 'correction_type' => null, 'effect_sign' => 1];
        }

        $originalId = (int) ($payload['original_document_id'] ?? 0);
        $correctionType = (string) ($payload['correction_type'] ?? 'adjustment');

        if ($originalId < 1 || ! in_array($correctionType, ['adjustment', 'replacement'], true)) {
            throw ValidationException::withMessages([
                'original_document_id' => ['Chứng từ điều chỉnh cần chứng từ gốc và loại điều chỉnh hợp lệ.'],
            ]);
        }

        $original = AcctDocument::query()->lockForUpdate()->findOrFail($originalId);

        if ((int) $original->organization_id !== $organizationId
            || $original->workflow_status !== 'posted'
            || $original->correction_type === 'reversal'
            || $original->direction !== ($payload['direction'] ?? $original->direction)
            || $original->currency !== strtoupper((string) ($payload['currency'] ?? $original->currency))) {
            throw ValidationException::withMessages([
                'original_document_id' => ['Chứng từ gốc phải đã ghi sổ và thuộc cùng pháp nhân.'],
            ]);
        }

        return [
            'original_document_id' => $originalId,
            'correction_type' => $correctionType,
            'effect_sign' => $documentType === 'credit_note' ? -1 : 1,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function assertClientCannotGrantTaxEligibility(array $payload): void
    {
        if (($payload['tax_eligibility'] ?? 'not_assessed') === 'eligible') {
            throw ValidationException::withMessages([
                'tax_eligibility' => ['Chỉ quy trình đánh giá thuế mới được xác nhận đủ điều kiện khấu trừ.'],
            ]);
        }
    }

    private function assertPeriodOpen(int $organizationId, ?string $documentDate): void
    {
        if ($this->periodIsLocked($organizationId, $documentDate)) {
            throw ValidationException::withMessages([
                'document_date' => ['Kỳ thuế chứa ngày chứng từ đã khóa hoặc đã nộp.'],
            ]);
        }
    }

    private function periodIsLocked(int $organizationId, ?string $documentDate): bool
    {
        if ($documentDate === null || $documentDate === '') {
            return false;
        }

        return AcctTaxPeriod::query()
            ->where('organization_id', $organizationId)
            ->whereDate('start_date', '<=', $documentDate)
            ->whereDate('end_date', '>=', $documentDate)
            ->whereIn('status', ['locked', 'filed'])
            ->exists();
    }

    private function decimal(mixed $value, int $scale, string $field): BigDecimal
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([$field => ['Giá trị số là bắt buộc.']]);
        }

        try {
            return BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HalfUp);
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => ['Giá trị số không hợp lệ.']]);
        }
    }

    private function existingIdempotentDocument(int $organizationId, mixed $idempotencyKey): ?AcctDocument
    {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return null;
        }

        return AcctDocument::query()
            ->where('organization_id', $organizationId)
            ->where('idempotency_key', (string) $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string,mixed> $payload */
    private function fingerprint(array $payload): string
    {
        unset($payload['idempotency_key']);

        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $child) {
            $value[$key] = $this->canonicalize($child);
        }

        return $value;
    }

    private function snapshotHash(AcctDocument $document): string
    {
        return hash('sha256', json_encode($this->canonicalize([
            'seller' => $document->seller_snapshot,
            'buyer' => $document->buyer_snapshot,
            'direction' => $document->direction,
            'document_type' => $document->document_type,
            'original_document_id' => $document->original_document_id,
            'correction_type' => $document->correction_type,
            'effect_sign' => $document->effect_sign,
            'document_no' => $document->document_no,
            'document_date' => $document->document_date?->toDateString(),
            'currency' => $document->currency,
            'exchange_rate' => (string) $document->exchange_rate,
            'lines' => $document->lines->map(fn (AcctDocumentLine $line): array => Arr::only($line->toArray(), [
                'line_type', 'item_kind', 'name', 'sku', 'unit', 'quantity', 'unit_price', 'line_subtotal',
                'discount_amount', 'tax_category', 'tax_rate', 'tax_base', 'tax_amount', 'line_total', 'snapshot',
            ]))->all(),
        ]), JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $payload */
    private function recordEvent(
        AcctDocument $document,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $adminId,
        ?string $idempotencyKey = null,
        array $payload = [],
    ): void {
        AcctDocumentEvent::query()->create([
            'document_id' => $document->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_admin_id' => $adminId,
            'document_version' => $document->version,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
        ]);
    }

    /** @return array<string,mixed> */
    private function auditSnapshot(AcctDocument $document): array
    {
        return Arr::only($document->toArray(), [
            'organization_id', 'party_id', 'direction', 'document_type', 'document_no', 'document_date',
            'currency', 'workflow_status', 'payment_status', 'legal_status', 'tax_eligibility', 'subtotal',
            'discount_total', 'tax_total', 'grand_total', 'base_tax_total', 'base_grand_total',
            'paid_amount', 'effect_sign', 'version',
        ]);
    }
}
