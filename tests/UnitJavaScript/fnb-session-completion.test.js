import { test } from 'node:test';
import assert from 'node:assert/strict';
import { kitchenLineAction, pendingSessionOrders } from '../../resources/admin/src/modules/fnb-pos/utils/fnbSessionCompletion.js';

test('paid bills do not hide unfinished service or draft orders', () => {
    const groups = pendingSessionOrders({ checks: [{ status: 'closed' }], orders: [
        { id: 1, lifecycle_status: 'active', lines: [{ id: 11, status: 'ready' }, { id: 12, status: 'served' }, { id: 13, status: 'voided' }] },
        { id: 2, lifecycle_status: 'draft', lines: [{ id: 21, status: 'draft' }] },
        { id: 3, lifecycle_status: 'completed', lines: [{ id: 31, status: 'served' }] },
    ] });
    assert.deepEqual(groups.map(({ lines }) => lines.map(({ id }) => id)), [[11], [21]]);
    assert.equal(groups[1].draft, true);
});

test('empty active and draft orders still prevent table release until server resolves them', () => {
    assert.equal(pendingSessionOrders({ orders: [{ status: 'draft', lines: [] }, { status: 'active', lines: [{ status: 'served' }] }] }).length, 2);
    assert.deepEqual(pendingSessionOrders(null), []);
    assert.deepEqual(pendingSessionOrders({ orders: [{ status: 'completed' }, { status: 'voided' }] }), []);
});

test('quick actions advance one kitchen step and never skip to served', () => {
    assert.equal(kitchenLineAction('waiting').status, 'preparing');
    assert.equal(kitchenLineAction('preparing').status, 'ready');
    assert.equal(kitchenLineAction('ready').status, 'served');
    for (const status of ['draft', 'served', 'voided', 'cancelled_compensated', 'unknown']) assert.equal(kitchenLineAction(status), null);
});
