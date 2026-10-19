import { test } from 'node:test';
import assert from 'node:assert/strict';
import { sessionDisplayStatus } from '../../resources/admin/src/modules/fnb-pos/utils/fnbSessionStatus.js';

const paidSession = () => ({
    status: 'settling',
    orders: [{ lifecycle_status: 'active', lines: [{ id: 1, status: 'ready', ordered_quantity: '1.000000', voided_quantity: '0.000000' }] }],
    checks: [{ status: 'closed', lines: [{ order_line_id: 1, allocated_quantity: '1.000000' }] }],
});

test('paid sessions show payment completion before service or table release finishes', () => {
    assert.equal(sessionDisplayStatus(paidSession()), 'paid');
    assert.equal(sessionDisplayStatus({ ...paidSession(), status: 'open' }), 'paid');
});

test('reserved capacity and partial payment do not imply completed payment', () => {
    const session = paidSession();
    session.checks[0] = { ...session.checks[0], status: 'finalized', available_to_collect_minor: 0, balance_due_minor: 30000, payments: [{ status: 'processing' }] };
    assert.equal(sessionDisplayStatus(session), 'settling');
    session.checks[0].payments[0].status = 'succeeded';
    session.checks[0].balance_due_minor = 15000;
    assert.equal(sessionDisplayStatus(session), 'settling');
});

test('every bill must close, including split bills', () => {
    const session = paidSession();
    session.checks.push({ status: 'open', lines: [] });
    assert.equal(sessionDisplayStatus(session), 'settling');
});

test('new unbilled quantities keep the session unpaid even after older bills close', () => {
    for (const lifecycle_status of ['draft', 'active']) {
        const session = paidSession();
        session.orders.push({ lifecycle_status, lines: [{ id: 2, quantity: '1' }] });
        assert.equal(sessionDisplayStatus(session), 'settling');
    }
});

test('closed split allocations cover fractional historical quantities exactly', () => {
    const session = paidSession();
    session.checks[0].lines[0].allocated_quantity = '0.4';
    session.checks.push({ status: 'closed', lines: [{ order_line_id: 1, allocated_quantity: '0.6' }] });
    assert.equal(sessionDisplayStatus(session), 'paid');
    session.checks[0].lines[0].allocated_quantity = '0.399999';
    assert.equal(sessionDisplayStatus(session), 'settling');
});

test('void bills cannot cover new quantities and voided order quantities need no payment', () => {
    const session = paidSession();
    session.orders[0].lines.push({ id: 2, quantity: '1', voided_quantity: '0' });
    session.checks.push({ status: 'void', lines: [{ order_line_id: 2, allocated_quantity: '1' }] });
    assert.equal(sessionDisplayStatus(session), 'settling');
    session.orders[0].lines[1].voided_quantity = '1';
    assert.equal(sessionDisplayStatus(session), 'paid');
});

test('empty sessions, invalid quantities and terminal states retain their lifecycle label', () => {
    assert.equal(sessionDisplayStatus({ status: 'open', checks: [] }), 'open');
    const session = paidSession();
    session.orders[0].lines[0].ordered_quantity = 'invalid';
    assert.equal(sessionDisplayStatus(session), 'settling');
    for (const status of ['closed', 'cancelled', 'merged']) assert.equal(sessionDisplayStatus({ ...paidSession(), status }), status);
});
