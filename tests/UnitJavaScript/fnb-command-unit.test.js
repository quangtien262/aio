import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { beginCommand, finishCommand, pendingCommands, prepareCommand } from '../../resources/admin/src/modules/fnb-pos/state/fnbCommandJournal.js';
import { isZeroCharge, paymentDefaults, paymentMethodAllowed, paymentMethodLabel, paymentModeForMethod, preparePaymentParts, rebalancePaymentParts, serverAmountDue, tenderedAmount } from '../../resources/admin/src/modules/fnb-pos/utils/fnbPayment.js';

const storage = new Map();
globalThis.localStorage = { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) };
if (!globalThis.crypto?.subtle) Object.defineProperty(globalThis, 'crypto', { value: webcrypto });
beforeEach(() => storage.clear());
const command = (overrides = {}) => ({ identity: 'website-a:actor-7', outletId: '10', terminalId: '20', path: 'sessions', method: 'POST', payload: { service_type: 'counter', customer_name: 'PRIVATE-CUSTOMER', amount_minor: 58239 }, idempotencyKey: 'original-key', ...overrides });

test('unknown network outcome reuses the exact key after a fresh submission', async () => {
    const first = await beginCommand(command());
    await finishCommand(first, { status: 0, requestId: 'request-a' });
    const retry = await beginCommand(command({ idempotencyKey: 'new-key' }));
    assert.equal(retry.idempotencyKey, 'original-key');
    assert.equal(pendingCommands('website-a:actor-7').length, 1);
    await finishCommand(retry);
    assert.equal(pendingCommands('website-a:actor-7').length, 0);
});

test('journal isolates website, actor, outlet and terminal', async () => {
    const first = await beginCommand(command());
    await finishCommand(first, { status: 503 });
    for (const changed of [{ identity: 'website-b:actor-7' }, { identity: 'website-a:actor-8' }, { outletId: '11' }, { terminalId: '21' }]) {
        const entry = await beginCommand(command({ ...changed, idempotencyKey: 'different-key' }));
        assert.equal(entry.idempotencyKey, 'different-key');
        assert.notEqual(entry.fingerprint, first.fingerprint);
    }
});

test('durable journal contains no customer or payment payload', async () => {
    const entry = await beginCommand(command());
    await finishCommand(entry, { status: 500 });
    const saved = [...storage.values()].join('');
    assert.ok(!saved.includes('PRIVATE-CUSTOMER'));
    assert.ok(!saved.includes('58239'));
    assert.ok(!saved.includes('customer_name'));
});

test('payload key ordering does not create another command; changed payload does', async () => {
    const first = await beginCommand(command({ payload: { quantity: '1', variant_id: 4 } }));
    await finishCommand(first, { status: 408 });
    const reordered = await beginCommand(command({ payload: { variant_id: 4, quantity: '1' }, idempotencyKey: 'retry' }));
    assert.equal(reordered.idempotencyKey, 'original-key');
    const changed = await beginCommand(command({ payload: { quantity: '2', variant_id: 4 }, idempotencyKey: 'changed' }));
    assert.equal(changed.idempotencyKey, 'changed');
});

test('definitive validation failures release identity; retryable rate limit retains it', async () => {
    const invalid = await beginCommand(command());
    await finishCommand(invalid, { status: 422 });
    const corrected = await beginCommand(command({ idempotencyKey: 'corrected' }));
    assert.equal(corrected.idempotencyKey, 'corrected');
    await finishCommand(corrected, { status: 429 });
    assert.equal((await beginCommand(command())).idempotencyKey, 'corrected');
});

test('a lost partial payment followed by refreshed check versions cannot collect the same amount again', async () => {
    const payload = { shift_id: 1, payment_method_id: 2, amount_minor: 18000, reference: 'PRIVATE-TRANSFER', expected_version: 3, expected_versions: { 'check:bill-42': 3 } };
    const original = command({ path: 'checks/42/payments', payload });
    let paid = 0;
    let mutationCount = 0;
    const committedKeys = new Set();
    const submit = async (input) => {
        const prepared = await prepareCommand(input, async (pending) => ({ data: { status: committedKeys.has(pending.idempotencyKey) ? 'committed' : 'unknown' } }));
        mutationCount += 1;
        if (!committedKeys.has(prepared.entry.idempotencyKey)) {
            paid += prepared.payload.amount_minor;
            committedKeys.add(prepared.entry.idempotencyKey);
        }
        // The server committed, but the browser never received its response.
        await finishCommand(prepared.entry, { status: 0 });
    };
    await submit(original);
    await assert.rejects(submit({ ...original, idempotencyKey: 'new-key-after-refresh', payload: { ...payload, expected_version: 4, expected_versions: { 'check:bill-42': 4 } } }),
        (error) => error.status === 409 && error.code === 'FNB_PREVIOUS_COMMAND_COMMITTED');
    assert.equal(mutationCount, 1);
    assert.equal(paid, 18000);
    assert.equal(pendingCommands(original.identity).length, 0);
    assert.ok(![...storage.values()].join('').includes('PRIVATE-TRANSFER'));
    assert.ok(![...storage.values()].join('').includes('18000'));
});

test('unknown version-only refresh retries the original body and key, then a definitive conflict releases it', async () => {
    const original = command({ path: 'checks/42/payments', payload: { amount_minor: 18000, expected_version: 3, expected_versions: { 'check:bill-42': 3 } } });
    const first = await beginCommand(original);
    await finishCommand(first, { status: 503 });
    const saved = [...storage.values()].join('');
    assert.ok(!saved.includes('amount_minor'));
    assert.ok(!saved.includes('18000'));
    const refreshed = { ...original, idempotencyKey: 'refreshed-key', payload: { ...original.payload, expected_version: 4, expected_versions: { 'check:bill-42': 4 } } };
    const retry = await prepareCommand(refreshed, async (pending) => {
        assert.equal(pending.idempotencyKey, 'original-key');
        return { data: { status: 'unknown' } };
    });
    assert.equal(retry.entry.idempotencyKey, 'original-key');
    assert.deepEqual(retry.payload, original.payload);
    await finishCommand(retry.entry, { status: 409 });
    const corrected = await prepareCommand(refreshed, () => assert.fail('A definitive stale-version rejection has already resolved the old command.'));
    assert.equal(corrected.entry.idempotencyKey, 'refreshed-key');
    assert.deepEqual(corrected.payload, refreshed.payload);
});

test('unknown changed payment or refund intent stays pending, while exact retries and unrelated bills remain usable', async () => {
    for (const path of ['checks/42/payments', 'payments/8/refunds']) {
        const original = command({ path, payload: { amount_minor: 18000, reason: 'PRIVATE-REASON' } });
        const first = await beginCommand(original);
        await finishCommand(first, { status: 0 });
        await assert.rejects(prepareCommand({ ...original, payload: { amount_minor: 5000, reason: 'Changed' }, idempotencyKey: 'changed' }, async () => ({ data: { status: 'unknown' } })),
            (error) => error.status === 409 && error.code === 'FNB_PREVIOUS_COMMAND_UNCERTAIN');
        assert.ok(pendingCommands(original.identity).some((entry) => entry.idempotencyKey === 'original-key' && entry.path === path));
        const exact = await prepareCommand({ ...original, idempotencyKey: 'retry' }, () => assert.fail('Exact retries retain the old key without inventing another operation.'));
        assert.equal(exact.entry.idempotencyKey, 'original-key');
        const otherBill = await prepareCommand({ ...original, path: path.replace(/42|8/, '99'), idempotencyKey: 'other-bill' });
        assert.equal(otherBill.entry.idempotencyKey, 'other-bill');
    }
    assert.ok(![...storage.values()].join('').includes('PRIVATE-REASON'));
});

test('late network failure cannot resurrect a server-confirmed pending command', async () => {
    const first = await beginCommand(command({ path: 'checks/42/payments' }));
    await finishCommand(first);
    await finishCommand(first, { status: 0 });
    assert.equal(pendingCommands(first.identity).length, 0);
});

test('partial mixed bill resumes its immutable plan and correct remaining amount', () => {
    const result = paymentDefaults({ settlement_mode: 'mixed', settlement_total_minor: 58000, projection: { gross_paid_total_minor: 40000 } }, [{ id: 1, kind: 'cash' }, { id: 2, kind: 'transfer' }]);
    assert.equal(result.settlement_mode, 'mixed');
    assert.equal(result.amount_minor, 18000);
});

test('one payment choice derives the settlement plan and respects an acknowledged plan', () => {
    const cash = { id: 1, kind: 'cash', name: 'Cash' };
    const transfer = { id: 2, kind: 'transfer', name: 'Bank' };
    const card = { id: 3, kind: 'card', name: 'Card' };
    const methods = [cash, transfer, card];
    assert.deepEqual(methods.map((method) => paymentMethodLabel(method, methods)), ['Tiền mặt', 'Chuyển khoản', 'Cà thẻ']);
    assert.deepEqual(methods.map((method) => paymentModeForMethod({}, method)), ['single_cash', 'single_non_cash', 'single_non_cash']);
    for (const method of methods) assert.equal(paymentMethodAllowed({}, method), true);
    assert.equal(paymentMethodAllowed({ settlement_mode: 'single_cash' }, transfer), false);
    assert.equal(paymentMethodAllowed({ settlement_mode: 'single_non_cash' }, cash), false);
    assert.equal(paymentMethodAllowed({ settlement_mode: 'single_non_cash' }, card), true);
    for (const method of methods) {
        assert.equal(paymentMethodAllowed({ settlement_mode: 'mixed' }, method), true);
        assert.equal(paymentModeForMethod({ settlement_mode: 'mixed' }, method), 'mixed');
    }
    assert.equal(paymentMethodLabel(transfer, [...methods, { id: 4, kind: 'transfer', name: 'Other bank' }]), 'Chuyển khoản · Bank');
});

test('payment uses locked cash rounding and recognizes zero-charge bills', () => {
    assert.equal(serverAmountDue({ grand_total_minor: 12300, settlement_total_minor: 12500 }), 12500);
    assert.equal(serverAmountDue({ settlement_total_minor: 0 }), 0);
    assert.equal(isZeroCharge({ settlement_total_minor: 0 }), true);
    assert.equal(isZeroCharge({ settlement_total_minor: 100, available_to_collect_minor: 0, balance_due_minor: 100 }), false);
    assert.equal(serverAmountDue({ settlement_total_minor: 100, available_to_collect_minor: 0, balance_due_minor: 100 }), 0);
});

test('combined tenders must balance and never send hidden cash or account fields', () => {
    const methods = [{ id: 1, kind: 'cash' }, { id: 2, kind: 'transfer', requires_reference: true }, { id: 3, kind: 'card', requires_reference: true }];
    const check = { grand_total_minor: 110000 };
    const rows = [{ payment_method_id: 1, amount_minor: 50000, tendered_minor: 100000 }, { payment_method_id: 2, amount_minor: 60000, tendered_minor: 200000, bank_account_id: 99, reference: 'QR-TEST' }];
    const prepared = preparePaymentParts(check, rows, methods);
    assert.equal(prepared.settlement_mode, 'mixed');
    assert.equal(prepared.payments[0].tendered_minor, 100000);
    assert.equal(prepared.payments[1].tendered_minor, 60000);
    assert.equal(prepared.payments[1].bank_account_id, undefined);
    assert.throws(() => preparePaymentParts(check, [rows[0]], methods), /Tổng/);
    assert.throws(() => preparePaymentParts(check, [rows[0], { ...rows[1], amount_minor: 60001 }], methods), /Tổng/);
    assert.throws(() => preparePaymentParts(check, [rows[0], { ...rows[1], reference: '' }], methods), /mã giao dịch/);
    assert.throws(() => preparePaymentParts(check, [rows[0], { ...rows[1], payment_method_id: 1 }], methods), /trùng/);
    assert.throws(() => preparePaymentParts(check, [{ ...rows[0], tendered_minor: 49999 }, rows[1]], methods), /Khách đưa/);
    assert.throws(() => preparePaymentParts(check, [{ ...rows[0], amount_minor: 50000.5 }, rows[1]], methods), /số tiền nguyên/);
    assert.throws(() => preparePaymentParts({ ...check, settlement_mode: 'single_non_cash' }, rows, methods), /không hợp lệ/);
    assert.equal(preparePaymentParts(check, [{ payment_method_id: 3, amount_minor: 110000, reference: 'CARD-TEST' }], methods).settlement_mode, 'single_non_cash');
    assert.equal(paymentDefaults(check, methods).payments[0].tendered_minor, 110000);
});

test('amount edits balance the other tender while preserving manually entered cash and references', () => {
    const methods = [{ id: 1, kind: 'cash' }, { id: 2, kind: 'transfer' }, { id: 3, kind: 'card' }];
    const rows = [{ payment_method_id: 1, amount_minor: 65000, tendered_minor: 65000 }, { payment_method_id: 2, amount_minor: 65000, reference: 'QR' }];
    let next = rebalancePaymentParts(rows, 0, 50000, 130000, methods);
    assert.deepEqual(next.map((row) => row.amount_minor), [50000, 80000]);
    assert.equal(next[0].tendered_minor, 50000);
    assert.equal(next[1].reference, 'QR');
    assert.equal(rows[0].amount_minor, 65000);
    next = rebalancePaymentParts(rows, 1, 90000, 130000, methods);
    assert.deepEqual(next.map((row) => row.amount_minor), [40000, 90000]);
    assert.equal(next[0].tendered_minor, 40000);
    next = rebalancePaymentParts([{ ...rows[0], tendered_minor: 100000 }, rows[1]], 1, 90000, 130000, methods);
    assert.equal(next[0].tendered_minor, 100000);
    next = rebalancePaymentParts(rows, 0, null, 130000, methods);
    assert.equal(next[0].amount_minor, null);
    assert.equal(next[1].amount_minor, 65000);
    next = rebalancePaymentParts(rows, 0, 140000, 130000, methods);
    assert.equal(next[1].amount_minor, 65000);
    const three = [...rows, { payment_method_id: 3, amount_minor: 30000 }];
    next = rebalancePaymentParts(three, 0, 50000, 130000, methods);
    assert.deepEqual(next.map((row) => row.amount_minor), [50000, 65000, 15000]);
    next = rebalancePaymentParts(three, 0, 100000, 130000, methods);
    assert.deepEqual(next.map((row) => row.amount_minor), [100000, 30000, 0]);
    assert.equal(next.reduce((total, row) => total + row.amount_minor, 0), 130000);
});

test('uncertain combined payment protects every tender from changed split retries', async () => {
    const original = command({ path: 'checks/42/payments', payload: { shift_id: 1, expected_version: 4, payments: [
        { payment_method_id: 1, amount_minor: 50000 }, { payment_method_id: 2, amount_minor: 60000, reference: 'PRIVATE-TRANSFER' },
    ] } });
    const first = await beginCommand(original);
    await finishCommand(first, { status: 0 });
    await assert.rejects(prepareCommand({ ...original, payload: { ...original.payload, payments: [
        { payment_method_id: 1, amount_minor: 40000 }, { payment_method_id: 2, amount_minor: 70000, reference: 'PRIVATE-TRANSFER' },
    ] } }, async () => ({ data: { status: 'unknown' } })), (error) => error.code === 'FNB_PREVIOUS_COMMAND_UNCERTAIN');
    const retry = await prepareCommand({ ...original, payload: { ...original.payload, expected_version: 5 } }, async () => ({ data: { status: 'unknown' } }));
    assert.equal(retry.entry.idempotencyKey, first.idempotencyKey);
    assert.equal(retry.payload.expected_version, 4);
    assert.ok(![...storage.values()].join('').includes('PRIVATE-TRANSFER'));
});

test('noncash payload never leaks a stale hidden cash tendered value', () => {
    assert.equal(tenderedAmount({ amount_minor: 18000, tendered_minor: 58000 }, { kind: 'transfer' }), 18000);
    assert.equal(tenderedAmount({ amount_minor: 18000, tendered_minor: 20000 }, { kind: 'cash' }), 20000);
});

test('partial refund sends the chosen decimal quantity and blocks reserved/exhausted quantities', async () => {
    const { refundAllocations } = await import('../../resources/admin/src/modules/fnb-pos/utils/fnbPayment.js');
    const lines = [{ id: 10, allocated_quantity: '3.000000', refundable_quantity: '1.500000' }, { id: 11, allocated_quantity: '1.000000', refundable_quantity: '0.000000' }];
    assert.deepEqual(refundAllocations(lines, [10], { 10: '0.500001' }), [{ check_line_id: 10, quantity: '0.500001' }]);
    assert.throws(() => refundAllocations(lines, [10], { 10: '1.500001' }), /vượt/);
    assert.throws(() => refundAllocations(lines, [11], { 11: '0.000001' }), /vượt/);
    assert.throws(() => refundAllocations(lines, [10], { 10: '-1' }), /vượt/);
    assert.throws(() => refundAllocations(lines, [10], { 10: '0.0000001' }), /vượt/);
});

test('naive SQL timestamps are UTC and render in the requested outlet timezone', async () => {
    const { parseServerDate, formatDateTime, formatDuration, calendarDate } = await import('../../resources/admin/src/modules/fnb-pos/utils/fnbFormat.js');
    assert.equal(parseServerDate('2026-10-18 07:55:00').toISOString(), '2026-10-18T07:55:00.000Z');
    assert.equal(parseServerDate('2026-10-18T14:55:00+07:00').toISOString(), '2026-10-18T07:55:00.000Z');
    assert.match(formatDateTime('2026-10-18 07:55:00', { timeZone: 'Asia/Ho_Chi_Minh' }), /14:55/);
    assert.equal(formatDuration('2026-10-18 07:55:00', Date.parse('2026-10-18T07:56:00Z')), '1 phút');
    assert.equal(calendarDate('Asia/Ho_Chi_Minh', new Date('2026-10-17T20:00:00Z')), '2026-10-18');
    assert.equal(calendarDate('America/New_York', new Date('2026-10-17T20:00:00Z')), '2026-10-17');
});
