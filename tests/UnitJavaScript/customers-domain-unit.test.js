import assert from 'node:assert/strict';
import test from 'node:test';
import { customerFilterSnapshot, customerId, customerMergeFields, customerMigrationActions, mapCustomerImport, parseCustomerCsv, serializeCustomerMergeChoices, serializeCustomerProfile } from '../../resources/admin/src/modules/customers/utils/customerData.js';

test('CSV import preserves leading zeroes, quoted commas, escaped quotes and multiline values', () => {
    const parsed = parseCustomerCsv('\uFEFFname,phone,notes\r\n"Nguyễn, An",0900123456,"Dòng 1\nDòng ""hai"""\r\n');
    assert.deepEqual(mapCustomerImport(parsed, { name: 'name', phone: 'phone', notes: 'notes' }), [{ name: 'Nguyễn, An', phone: '0900123456', notes: 'Dòng 1\nDòng "hai"' }]);
    assert.throws(() => parseCustomerCsv('name,name\nA,B'), /trùng/);
    assert.throws(() => parseCustomerCsv('name,phone\nA'), /Số cột/);
    assert.throws(() => parseCustomerCsv('name\n"Chưa đóng'), /dấu nháy/);
    assert.throws(() => mapCustomerImport(parsed, { name: 'name', phone: 'name' }), /Mỗi trường/);
    assert.throws(() => mapCustomerImport(parsed, { name: 'notes' }), /Tên khách hàng/);
});

test('editing a masked profile never writes masked PII or unregistered and retired fields', () => {
    const values = { name: '  An  ', version: 99, public_id: 'do-not-write', contacts: [{ kind: 'phone', value: '***3456' }], addresses: [{ id: 8, profile_id: 6, line1: 'Protected' }], birthday: '2000-01-01', notes: 'Hidden', tax_code: 'Hidden', tags: ['Khách quen'], custom_fields: { 'customers.size': 'M', 'customers.private': 'hidden', 'customers.old': 'archived', 'unregistered.value': 'x' } };
    const fields = [{ namespace: 'customers', key: 'size', status: 'active', sensitive: false }, { namespace: 'customers', key: 'private', status: 'active', sensitive: true }, { namespace: 'customers', key: 'old', status: 'archived' }];
    const payload = serializeCustomerProfile(values, { profile: { version: 3 }, fields, sensitiveVisible: false });
    assert.deepEqual(payload, { name: 'An', expected_version: 3, tags: ['Khách quen'], custom_fields: { 'customers.size': 'M' } });
    const visible = serializeCustomerProfile(values, { fields, sensitiveVisible: true });
    assert.equal(visible.addresses[0].id, undefined);
    assert.equal(visible.addresses[0].profile_id, undefined);
    assert.equal(visible.public_id, undefined);
    assert.equal(customerId({ name: 'No public ID' }), '');
});

test('uncertain customer writes reuse a durable key without storing personal data and reject changed intent', async () => {
    const storage = new Map();
    globalThis.localStorage = { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) };
    const { createCustomersApi, pendingCustomerCommands } = await import('../../resources/admin/src/modules/customers/api/customersApi.js');
    const calls = [];
    const request = async (path, options) => { calls.push({ path, options }); throw new Error('Lost response'); };
    const body = { name: 'PRIVATE-NAME-654', phone: '0900987123', notes: 'PRIVATE-NOTE-987' };
    await assert.rejects(createCustomersApi(request, 'website-main:admin-2', 'book-a').command('books/book-a/profiles', body));
    const originalKey = calls[0].options.headers['Idempotency-Key'];
    await assert.rejects(createCustomersApi(request, 'website-main:admin-2', 'book-a').command('books/book-a/profiles', { phone: body.phone, notes: body.notes, name: body.name }));
    assert.equal(calls[1].options.headers['Idempotency-Key'], originalKey);
    await assert.rejects(createCustomersApi(request, 'website-main:admin-2', 'book-a').command('books/book-a/profiles', { name: 'Different' }), /chưa rõ kết quả/);
    assert.equal(calls.length, 2);
    const saved = [...storage.values()].join('');
    for (const secret of [body.name, body.phone, body.notes, 'contacts', 'policy_version']) assert.equal(saved.includes(secret), false);
    const other = createCustomersApi(async () => ({ data: { id: 'other' } }), 'website-main:admin-2', 'book-b');
    await other.command('books/book-b/profiles', body);
    assert.equal(pendingCustomerCommands('website-main:admin-2').length, 1);
    const success = createCustomersApi(async () => ({ data: { id: 'recovered' } }), 'website-main:admin-2', 'book-a');
    await success.command('books/book-a/profiles', body);
    assert.equal(pendingCustomerCommands('website-main:admin-2').length, 0);
});

test('read-only import and migration previews never leave a write blocked after a network failure', async () => {
    const { createCustomersApi, pendingCustomerCommands } = await import('../../resources/admin/src/modules/customers/api/customersApi.js');
    const api = createCustomersApi(async () => { throw new Error('Preview network failure'); }, 'preview-user', 'book-a');
    await assert.rejects(api.preview('books/book-a/imports', { rows: [{ name: 'An' }], commit: false }));
    assert.equal(pendingCustomerCommands('preview-user').length, 0);
});

test('merge choices require full visibility of both profiles and never combine consent', () => {
    const visible = { sensitive_visible: true };
    const masked = { sensitive_visible: false };
    assert.ok(customerMergeFields(visible, visible).some((field) => field.key === 'contacts'));
    assert.ok(!customerMergeFields(visible, masked).some((field) => ['contacts', 'addresses', 'notes', 'birthday', 'tax_code'].includes(field.key)));
    const choices = { name: 'source', contacts: 'combine', addresses: 'target', tags: 'combine', consents: 'combine', 'custom_fields.private': 'source' };
    assert.deepEqual(serializeCustomerMergeChoices(visible, visible, choices), { name: 'source', contacts: 'combine', addresses: 'target', tags: 'combine' });
    assert.deepEqual(serializeCustomerMergeChoices(visible, masked, choices), { name: 'source', tags: 'combine' });
    assert.throws(() => serializeCustomerMergeChoices(visible, visible, { name: 'combine' }), /không hợp lệ/);
});

test('migration can continue across committed batches but cannot cut over while any batch remains or state is unknown', () => {
    assert.deepEqual(customerMigrationActions(null), { canImport: false, canCutover: false });
    assert.deepEqual(customerMigrationActions({ valid: true, committed: true, has_more: true }), { canImport: true, canCutover: false });
    assert.deepEqual(customerMigrationActions({ valid: true, committed: true, has_more: false }), { canImport: false, canCutover: true });
    assert.equal(customerMigrationActions({ valid: true, committed: true }).canCutover, false);
    assert.deepEqual(customerMigrationActions({ valid: true, committed: true, has_more: false, cutover: true }), { canImport: false, canCutover: false });
    assert.equal(customerMigrationActions({ valid: false, has_more: false }).canCutover, false);
});

test('saved filters include only supported list criteria and restore safe defaults', () => {
    assert.deepEqual(customerFilterSnapshot({ q: '  0900123456  ', status: 'all', type: 'company', tag: ' VIP ', per_page: '50', contacts: ['not-a-filter'], page: 99 }), { q: '0900123456', status: 'all', type: 'company', tag: 'VIP', per_page: 50 });
    assert.deepEqual(customerFilterSnapshot({ status: 'invalid', type: 'invalid', per_page: 10000 }), { q: '', status: 'active', type: null, tag: null, per_page: 20 });
});
