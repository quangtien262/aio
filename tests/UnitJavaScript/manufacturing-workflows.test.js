import assert from 'node:assert/strict';
import test from 'node:test';
import { webcrypto } from 'node:crypto';
import { createManufacturingApi } from '../../resources/admin/src/modules/manufacturing/api/manufacturingApi.js';
import { commands, commandPayload, sectionForMenu, manufacturingOperationLabel } from '../../resources/admin/src/modules/manufacturing/utils/manufacturingForms.js';

if (!globalThis.crypto) Object.defineProperty(globalThis, 'crypto', { value: webcrypto });
const stored = new Map();
Object.defineProperty(globalThis, 'localStorage', { configurable: true, value: { getItem: (key) => stored.get(key) ?? null, setItem: (key, value) => stored.set(key, value) } });

test('uncertain command retry reuses its key and persists no business payload', async () => {
    stored.clear();
    const requests = [];
    const api = createManufacturingApi(async (url, options) => {
        requests.push({ url, options });
        if (requests.length === 1) throw new Error('Connection interrupted');
        return { data: { id: 'roll-result', version: 1 } };
    }, 1, 'factory-a');
    const payload = { customer_name: 'PRIVATE BUYER', notes: 'PRIVATE NOTES', lines: [{ color: 'NAVY', size: 'S', quantity: 12 }] };
    await assert.rejects(api.command('demand.save', null, payload));
    const pending = await api.pending();
    assert.equal(pending.length, 1);
    const journal = [...stored.values()].join('');
    assert.ok(!journal.includes('PRIVATE BUYER'));
    assert.ok(!journal.includes('PRIVATE NOTES'));
    assert.ok(!journal.includes('NAVY'));
    await api.command('demand.save', null, payload);
    assert.equal(requests[0].options.headers['Idempotency-Key'], requests[1].options.headers['Idempotency-Key']);
    assert.equal((await api.pending()).length, 0);
});

test('changed payload cannot bypass an uncertain write but a different factory is independent', async () => {
    stored.clear();
    let calls = 0;
    const call = async () => { calls += 1; throw new Error('timeout'); };
    const first = createManufacturingApi(call, 2, 'factory-a');
    await assert.rejects(first.command('bundle.handover', 'bundle-a', { quantity: 12 }));
    await assert.rejects(first.command('bundle.handover', 'bundle-a', { quantity: 13 }), /chưa rõ kết quả/);
    assert.equal(calls, 1);
    const other = createManufacturingApi(call, 2, 'factory-b');
    assert.equal((await other.pending()).length, 0);
    await assert.rejects(other.command('bundle.handover', 'bundle-a', { quantity: 13 }));
    assert.equal(calls, 2);
});

test('recovery only clears confirmed terminal results and list requests scope factory server side', async () => {
    stored.clear();
    let status = 'pending';
    const paths = [];
    const api = createManufacturingApi(async (url, options) => {
        paths.push(url);
        if (options?.method === 'POST') throw new Error('timeout');
        return { data: { status } };
    }, 3, 'factory-a');
    await assert.rejects(api.command('shipment.dispatch', 'carton-a', { reference: 'EXPRESS' }));
    const [entry] = await api.pending();
    await api.recover(entry);
    assert.equal((await api.pending()).length, 1);
    status = 'committed';
    await api.recover(entry);
    assert.equal((await api.pending()).length, 0);
    await api.read('orders', { page: 2, search: 'A B' });
    assert.ok(paths.at(-1).includes('factory_id=factory-a'));
    assert.ok(paths.at(-1).includes('page=2'));
    assert.ok(paths.at(-1).includes('search=A+B'));
});

test('rejected commands clear journal to allow correcting validation errors', async () => {
    stored.clear();
    const api = createManufacturingApi(async () => { const error = new Error('Invalid quantity'); error.status = 422; throw error; }, 4, 'factory-a');
    await assert.rejects(api.command('cut.create', null, { quantity: -1 }));
    assert.equal((await api.pending()).length, 0);
});

test('order matrix conserves variants and rejects duplicate or fractional units', () => {
    const base = { code: 'ORDER-1', demand_id: 'd', style_id: 's', workshop_id: 'w', planned_start: '2026-10-21', planned_end: '2026-10-25', lines: [{ color: 'Black', size: 'S', quantity: 30 }, { color: 'Black', size: 'M', quantity: 20 }] };
    const payload = commandPayload('order.save', { ...base, unexpected: 'discard' }, { version: 7 });
    assert.equal(payload.lines.reduce((total, row) => total + row.quantity, 0), 50);
    assert.equal(payload.expected_version, 7);
    assert.equal(payload.unexpected, undefined);
    assert.throws(() => commandPayload('order.save', { ...base, lines: [...base.lines, base.lines[0]] }), /chỉ được khai báo một lần/);
    assert.throws(() => commandPayload('order.save', { ...base, lines: [{ color: 'Black', size: 'S', quantity: 0.5 }] }), /không hợp lệ/);
    assert.throws(() => commandPayload('order.save', { ...base, planned_end: '2026-10-20' }), /không được trước/);
});

test('QC and subcontract receipt require explicit conservation of all outcomes', () => {
    const result = { checkpoint: 'final', quantity: 30, good_quantity: 26, rework_quantity: 3, scrap_quantity: 1, bundle_id: 'b' };
    assert.equal(commandPayload('quality.inspect', result).quantity, 30);
    assert.equal(commandPayload('subcontract.receive', result).good_quantity, 26);
    assert.throws(() => commandPayload('quality.inspect', { ...result, good_quantity: 27 }), /phải bằng/);
    assert.throws(() => commandPayload('quality.rework', { bundle_id: 'b', quantity: 3, good_quantity: 2, scrap_quantity: 0 }), /phải bằng/);
});

test('purchase lines are not interpreted as size/color matrices and opening stock requires reason', () => {
    const payload = commandPayload('procurement.create', { supplier_name: 'Supplier', lines: [{ material_code: 'COTTON', name: 'Cotton', uom: 'm', quantity: 100, unit_cost: 40000 }, { material_code: 'THREAD', name: 'Thread', uom: 'cone', quantity: 20, unit_cost: 15000 }] });
    assert.equal(payload.lines.length, 2);
    assert.throws(() => commandPayload('material.receive', { code: 'C', name: 'Cotton', roll_code: 'R1', lot_code: 'L1', shade: 'A', quantity: 100, uom: 'm', unit_cost: 10 }), /lý do/);
});

test('every action uses an existing native permission and menu sections resolve by key or route', () => {
    const valid = new Set(['manufacturing.settings', 'manufacturing.engineering', 'manufacturing.plan', 'manufacturing.execute', 'manufacturing.quality', 'manufacturing.fulfill', 'manufacturing.cost.manage']);
    Object.values(commands).forEach((command) => assert.ok(valid.has(command.permission), command.permission));
    assert.equal(sectionForMenu({ key: 'manufacturing-quality' }), 'quality');
    assert.equal(sectionForMenu({ key: 'manufacturing-demands' }), 'demand');
    assert.equal(sectionForMenu({ key: 'manufacturing-subcontracts' }), 'subcontract');
    assert.equal(sectionForMenu({ key: 'custom', route: '/admin/modules/manufacturing/materials' }), 'materials');
    assert.equal(sectionForMenu({ key: 'manufacturing' }), 'overview');
});

test('cutting uses exactly one existing material allocation source per BOM row', () => {
    const base = { order_id: 'o', workshop_id: 'w', code: 'CUT', color: 'Black', size: 'M', quantity: 10 };
    for (const source of ['material_id', 'issue_id', 'reservation_id']) {
        const payload = commandPayload('cut.create', { ...base, materials: [{ [source]: 'allocation', consumed_quantity: 20 }] });
        assert.equal(payload.materials[0][source], 'allocation');
    }
    assert.throws(() => commandPayload('cut.create', { ...base, materials: [{ material_id: 'r', issue_id: 'i', consumed_quantity: 20 }] }), /đúng một nguồn/);
    assert.throws(() => commandPayload('cut.create', { ...base, materials: [{ consumed_quantity: 20 }] }), /đúng một nguồn/);
});

test('merge keeps independent expected versions for target and source bundles', () => {
    const payload = commandPayload('bundle.merge', { source_bundle_id: 'source', source_expected_version: 6, expected_version: 99 }, { version: 4 });
    assert.equal(payload.expected_version, 4);
    assert.equal(payload.source_expected_version, 6);
});

test('inline QC cannot scrap and manual calendar requires the selected scope', () => {
    const qc = { checkpoint: 'inline', bundle_id: 'b', quantity: 10, good_quantity: 8, rework_quantity: 1, scrap_quantity: 1 };
    assert.throws(() => commandPayload('quality.inspect', qc), /kiểm cuối/);
    assert.equal(commandPayload('quality.inspect', { ...qc, good_quantity: 9, scrap_quantity: 0 }).checkpoint, 'inline');
    const calendar = { scope_type: 'line', work_date: '2026-10-21', is_working: false, available_minutes: 0 };
    assert.throws(() => commandPayload('plan.calendar.save', calendar), /phạm vi lịch/);
    const payload = commandPayload('plan.calendar.save', { ...calendar, line_id: 'line-uuid' });
    assert.equal(payload.is_working, false);
    assert.equal(payload.available_minutes, '0');
});

test('material quantities and monetary inputs preserve six decimals beyond safe integer range', () => {
    const payload = commandPayload('costing.record', { order_id: 'o', category: 'overhead', amount: '9007199254740993.123456', notes: 'Approved allocation' });
    assert.equal(payload.amount, '9007199254740993.123456');
    assert.throws(() => commandPayload('costing.record', { order_id: 'o', category: 'overhead', amount: '1.0000001', notes: 'Too precise' }), /tối đa 6/);
});

test('stocktake counts send only hidden references and entered quantities, never labels', () => {
    const payload = commandPayload('material.stocktake.post', { stocktake_id: 'take', counts: [{ balance_id: 12, label: 'PRIVATE ROLL LABEL', expected_quantity: '100', quantity: '99.123456', secondary_quantity: '8.5' }] }, { version: 2 });
    assert.deepEqual(payload.counts, [{ balance_id: 12, quantity: '99.123456', secondary_quantity: '8.5' }]);
    assert.equal(commands['material.stocktake.post'].apiOperation, 'material.stocktake');
    assert.equal(payload.expected_version, 2);
});

test('document retry derives the same key without storing document content', async () => {
    stored.clear();
    const keys = [];
    const api = createManufacturingApi(async (_, options) => { keys.push(options.headers['Idempotency-Key']); assert.ok(options.body instanceof FormData); if (keys.length === 1) throw new Error('timeout'); return { data: { id: 'document-id' } }; }, 8, 'factory-a');
    const file = new File(['PRIVATE TECH PACK CONTENT'], 'PRIVATE-SPEC.txt', { type: 'text/plain' });
    await assert.rejects(api.uploadDocument('style', 'style-id', file));
    await api.uploadDocument('style', 'style-id', file);
    assert.equal(keys[0], keys[1]);
    assert.ok(![...stored.values()].join('').includes('PRIVATE'));
});


test('operation and outbox labels resolve native and prefixed commands', () => {
    assert.equal(manufacturingOperationLabel('manufacturing.line.create'), 'Tạo chuyền sản xuất');
    for (const [operation, definition] of Object.entries(commands)) {
        const label = manufacturingOperationLabel(operation);
        assert.equal(manufacturingOperationLabel(`manufacturing.${operation}`), label);
        assert.equal(label, ({ 'line.create': 'Tạo chuyền sản xuất', 'material.stocktake': 'Kiểm kê nguyên phụ liệu' })[operation] ?? definition.title);
    }
    assert.equal(manufacturingOperationLabel('manufacturing.material.stocktake'), 'Kiểm kê nguyên phụ liệu');
    assert.equal(manufacturingOperationLabel(null), '—');
    assert.equal(manufacturingOperationLabel('manufacturing.future.event'), 'manufacturing.future.event');
});


test('blank create codes are sent for server allocation while existing material references remain explicit', () => {
    const opening = commandPayload('material.receive', { name: 'Vải mới', code: '  ', roll_code: '', lot_code: 'LOT-A', shade: 'A', quantity: '2.5', uom: 'm', unit_cost: '0.125', reason: 'Kiểm kê đầu kỳ' });
    assert.equal(opening.code, undefined);
    assert.equal(opening.roll_code, undefined);
    assert.equal(opening.name, 'Vải mới');
    assert.equal(opening.quantity, '2.5');
    assert.equal(commandPayload('shift.create', { name: 'Ca sáng', starts_at: '08:00', ends_at: '17:00' }).code, undefined);
    assert.equal(commandPayload('sku.save', { name: 'Vải mới', base_unit: 'm', tracking_mode: 'batch', quantity_scale: 6 }).sku, undefined);
    const style = { name: 'Áo', colors: ['Đen'], sizes: ['S'], bom: [{ material_code: 'VT-EXISTING', uom: 'm', quantity: 1 }], routing: [{ name: 'May', smv: 5 }] };
    const saved = commandPayload('style.save', style);
    assert.equal(saved.code, undefined);
    assert.equal(saved.routing[0].code, undefined);
    assert.equal(saved.bom[0].material_code, 'VT-EXISTING');
    assert.throws(() => commandPayload('style.save', { ...style, bom: [{ uom: 'm', quantity: 1 }] }), /mã vật tư/);
});
