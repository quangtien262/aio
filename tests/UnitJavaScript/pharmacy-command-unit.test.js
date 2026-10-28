import assert from 'node:assert/strict';
import test from 'node:test';
import { addDecimal, canViewPharmacySection, capabilityReady, decimalInput, resourceRows } from '../../resources/admin/src/modules/pharmacy/utils/pharmacyData.js';

function browserStorage() {
    const data = new Map();
    globalThis.localStorage = { getItem: (key) => data.get(key) ?? null, setItem: (key, value) => data.set(key, value) };
    return data;
}
const fresh = async () => import(`../../resources/admin/src/modules/pharmacy/api/pharmacyApi.js?test=${globalThis.crypto.randomUUID()}`);

test('uncertain command persists no patient, prescription or tender payload and reuses original key', async () => {
    const storage = browserStorage();
    const { createPharmacyApi } = await fresh();
    const attempts = [];
    let fail = true;
    const api = createPharmacyApi(async (url, options) => {
        attempts.push({ url, options });
        if (fail) throw new Error('Connection lost');
        return { data: { id: 'record-1' } };
    }, 'actor-1', 'pharmacy-1');
    const body = { dispensing_id: 'private-record', patient_name: 'Sensitive Person', prescription: 'PRIVATE RX', tenders: [{ amount_minor: '40000' }] };
    await assert.rejects(api.command('dispensing.finalize', body), /Connection lost/);
    const durable = storage.get('aio.pharmacy.commands.v1');
    for (const privateValue of ['Sensitive Person', 'PRIVATE RX', '40000', 'private-record', 'actor-1', 'pharmacy-1']) assert.ok(!durable.includes(privateValue));
    assert.equal((await api.pending()).length, 1);
    fail = false;
    await api.command('dispensing.finalize', body);
    assert.equal(attempts[0].options.headers['Idempotency-Key'], attempts[1].options.headers['Idempotency-Key']);
    assert.equal((await api.pending()).length, 0);
});
test('changed financial intent remains blocked while original result is unknown', async () => {
    browserStorage();
    const { createPharmacyApi } = await fresh();
    const api = createPharmacyApi(async () => { throw new Error('Timeout'); }, 'actor', 'store');
    await assert.rejects(api.command('dispensing.finalize', { dispensing_id: 'one', amount_minor: '100' }));
    await assert.rejects(api.command('dispensing.finalize', { dispensing_id: 'one', amount_minor: '101' }), (error) => error.code === 'PHARMACY_COMMAND_UNCERTAIN');
});
test('recovery rejects a command from another actor or pharmacy context', async () => {
    browserStorage();
    const { createPharmacyApi } = await fresh();
    const first = createPharmacyApi(async () => { throw new Error('Timeout'); }, 'actor', 'store-a');
    await assert.rejects(first.command('dispensing.handover', { dispensing_id: 'one' }));
    const [entry] = await first.pending();
    const other = createPharmacyApi(async () => assert.fail('foreign recovery must not make request'), 'actor', 'store-b');
    assert.deepEqual(await other.pending(), []);
    await assert.rejects(other.recover(entry), /phạm vi khác/);
});
test('authoritative unknown remains pending; committed recovery clears original entry', async () => {
    browserStorage();
    const { createPharmacyApi } = await fresh();
    let status = 'unknown';
    const api = createPharmacyApi(async (_url, options) => {
        if (options?.method === 'POST') throw new Error('Timeout');
        return { data: { status } };
    }, 'actor', 'store');
    await assert.rejects(api.command('receipt.create', { quantity: '1' }));
    const [entry] = await api.pending();
    await api.recover(entry);
    assert.equal((await api.pending()).length, 1);
    status = 'committed'; await api.recover(entry);
    assert.equal((await api.pending()).length, 0);
});
test('definitive validation clears intent but paid uncertainty retains it', async () => {
    browserStorage();
    const { createPharmacyApi } = await fresh();
    let failure = Object.assign(new Error('Invalid'), { status: 422 });
    const api = createPharmacyApi(async () => { throw failure; }, 'actor', 'store');
    await assert.rejects(api.command('dispensing.finalize', { dispensing_id: 'one' }));
    assert.equal((await api.pending()).length, 0);
    failure = Object.assign(new Error('Needs reconciliation'), { status: 409, code: 'PAYMENT_PAID_LOCAL_FAILURE' });
    await assert.rejects(api.command('dispensing.finalize', { dispensing_id: 'one' }));
    assert.equal((await api.pending()).length, 1);
});
test('decimal input rejects float notation and preserves six-digit quantities', () => {
    assert.equal(decimalInput('0.000001'), '0.000001');
    assert.equal(addDecimal('0.100001', '0.200009'), '0.30001');
    assert.equal(addDecimal('999999999999.999999', '0.000001'), '1000000000000');
    for (const value of ['1e3', '-1', '0', '0.0000001', 'NaN']) assert.throws(() => decimalInput(value));
    assert.deepEqual(resourceRows({ records: [{ id: 'one' }], meta: {} }), [{ id: 'one' }]);
    assert.equal(capabilityReady({ enabled: true, ready: false }), false);
});
test('patient exact lookup keeps phone out of URLs and command storage and uses fresh read keys', async () => {
    const storage = browserStorage();
    const { createPharmacyApi } = await fresh(); const calls = [];
    const api = createPharmacyApi(async (url, options) => { calls.push({ url, options }); return { data: { records: [] } }; }, 'actor', 'store');
    await api.lookupPatients('0912345678'); await api.lookupPatients('0912345678');
    assert.equal(calls[0].url, '/admin/api/pharmacy/commands');
    assert.equal(JSON.parse(calls[0].options.body).phone, '0912345678');
    assert.notEqual(calls[0].options.headers['Idempotency-Key'], calls[1].options.headers['Idempotency-Key']);
    assert.deepEqual(await api.pending(), []);
    assert.ok(!JSON.stringify([...storage]).includes('0912345678'));
});
test('unknown persisted properties are stripped before the journal is written again', async () => {
    const storage = browserStorage(); const { createPharmacyApi } = await fresh();
    const api = createPharmacyApi(async () => { throw new Error('Timeout'); }, 'actor', 'store');
    await assert.rejects(api.command('dispensing.prepare', { dispensing_id: 'one' }));
    const rows = JSON.parse(storage.get('aio.pharmacy.commands.v1')); rows[0].payload = { patient_name: 'Never retain this' };
    storage.set('aio.pharmacy.commands.v1', JSON.stringify(rows));
    await assert.rejects(api.command('dispensing.review', { dispensing_id: 'two' }));
    assert.ok(!storage.get('aio.pharmacy.commands.v1').includes('Never retain this'));
});
test('read-only report role does not open clinical queues and print links stay private', async () => {
    assert.equal(canViewPharmacySection(['pharmacy.view', 'pharmacy.report.view'], 'reports'), true);
    assert.equal(canViewPharmacySection(['pharmacy.view', 'pharmacy.report.view'], 'work'), false);
    const { isPharmacyPrintJobUrl } = await fresh();
    assert.equal(isPharmacyPrintJobUrl('/admin/api/pharmacy/print-jobs/6e2064ad-70f7-4206-b701-22929b2ad932'), true);
    for (const value of ['https://example.com/admin/api/pharmacy/print-jobs/6e2064ad-70f7-4206-b701-22929b2ad932', '/admin/api/pharmacy/print-jobs/../patients', '/admin/api/pharmacy/print-jobs/6e2064ad-70f7-4206-b701-22929b2ad932?export=1']) assert.equal(isPharmacyPrintJobUrl(value), false);
});
