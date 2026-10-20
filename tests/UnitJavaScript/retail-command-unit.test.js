import assert from 'node:assert/strict';
import test from 'node:test';
import { addQuantity, estimateLine, formatDate, formatMoney, quantityMicros, quantityText, rows } from '../../resources/admin/src/modules/retail-pos/utils/retailFormat.js';

const storage = new Map();
globalThis.localStorage = {
    getItem: (key) => storage.get(key) ?? null,
    setItem: (key, value) => storage.set(key, value),
    removeItem: (key) => storage.delete(key),
};
const { beginCommand, resolveCommand, pendingCommands, definitiveFailure } = await import('../../resources/admin/src/modules/retail-pos/state/retailCommandJournal.js');

test('weighted quantities and amounts retain precision beyond JavaScript safe integers', () => {
    assert.equal(addQuantity('0.375', '0.125'), '0.5');
    assert.equal(quantityText(quantityMicros('48') - quantityMicros('9')), '39');
    assert.equal(estimateLine('0.375', '37500'), 14063n);
    assert.equal(estimateLine('1', '9007199254740993'), 9007199254740993n);
    assert.ok(formatMoney('9007199254740993').includes('9.007.199.254.740.993'));
    assert.throws(() => quantityMicros('0.0000001'));
    assert.throws(() => quantityMicros('-1'));
    assert.deepEqual(rows({ data: { error: 'not a list' } }), []);
    assert.equal(formatDate('2026-10-20 10:00:00'), formatDate('2026-10-20T10:00:00Z'));
});

test('lost payment response retains one operation and blocks collecting the same cart as another sale', async () => {
    const input = { identity: 'retail-payment-actor', branchId: 'branch-a', path: 'sales/sale-1/complete', payload: { expected_version: 2, tenders: [{ method: 'cash', amount_minor: '14063' }] } };
    const first = await beginCommand(input);
    const retried = await beginCommand({ ...input, payload: { tenders: [{ amount_minor: '14063', method: 'cash' }], expected_version: 2 } });
    assert.equal(retried.idempotencyKey, first.idempotencyKey);
    await assert.rejects(beginCommand({ ...input, path: 'sales/sale-2/complete' }), (error) => error.code === 'RETAIL_COMMAND_UNCERTAIN');
    assert.equal(pendingCommands(input.identity).length, 1);
    await resolveCommand(first);
    assert.equal(pendingCommands(input.identity).length, 0);
    const next = await beginCommand({ ...input, path: 'sales/sale-2/complete' });
    assert.notEqual(next.idempotencyKey, first.idempotencyKey);
    await resolveCommand(next);
});

test('journal contains no customer details or tender payload and isolates branch and actor', async () => {
    const input = { identity: 'retail-privacy-actor', branchId: 'branch-a', path: 'returns/return-1/refund', payload: { customer_name: 'PRIVATE-CUSTOMER-928', reference: 'PRIVATE-BANK-531', amount_minor: '987654321' } };
    const first = await beginCommand(input);
    const otherBranch = await beginCommand({ ...input, branchId: 'branch-b' });
    const otherActor = await beginCommand({ ...input, identity: 'retail-other-actor' });
    assert.notEqual(first.idempotencyKey, otherBranch.idempotencyKey);
    assert.notEqual(first.idempotencyKey, otherActor.idempotencyKey);
    const saved = [...storage.values()].join('');
    for (const value of ['PRIVATE-CUSTOMER-928', 'PRIVATE-BANK-531', '987654321', 'customer_name', 'amount_minor']) assert.ok(!saved.includes(value));
    for (const entry of [first, otherBranch, otherActor]) await resolveCommand(entry);
});

test('uncertain financial responses remain protected while definitive validation failures may be corrected', () => {
    assert.equal(definitiveFailure({ status: 503 }), false);
    assert.equal(definitiveFailure({ status: 409, code: 'RETAIL_PAYMENT_UNCERTAIN' }), false);
    assert.equal(definitiveFailure({ status: 409, code: 'RETAIL_STOCK_ATTENTION' }), false);
    assert.equal(definitiveFailure({ status: 422 }), true);
    assert.equal(definitiveFailure({ status: 429 }), false);
});
