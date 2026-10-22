import assert from 'node:assert/strict';
import test from 'node:test';
import { buildRetailNavigation } from '../../resources/admin/src/modules/retail-pos/utils/retailNavigation.js';

const leaves = (items) => items.flatMap((item) => item.children ? leaves(item.children) : [item.key]);

test('POS navigation integrates shift management without a duplicate shift link', () => {
    const items = [{ key: 'retail-pos' }, { key: 'retail-shifts' }, { key: 'retail-branches' }];
    assert.deepEqual(leaves(buildRetailNavigation(items)), ['retail-pos', 'retail-branches']);
    assert.equal(items.length, 3);
});

test('shift-only roles keep their existing route without gaining POS access', () => {
    assert.deepEqual(leaves(buildRetailNavigation([{ key: 'retail-shifts' }])), ['retail-shifts']);
});

test('integrating shifts does not invent any permission-filtered destinations', () => {
    assert.deepEqual(leaves(buildRetailNavigation([{ key: 'retail-pos' }, { key: 'retail-settings' }])), ['retail-pos', 'retail-settings']);
});

test('overview opens directly using the existing dashboard link and has no submenu', () => {
    const onClick = () => {};
    const menu = buildRetailNavigation([{ key: 'retail-reports', path: '/reports' }, { key: 'retail-dashboard', path: '/dashboard', onClick }]);
    assert.equal(menu.length, 1);
    assert.equal(menu[0].key, 'retail-dashboard');
    assert.equal(menu[0].label, 'Tổng quan');
    assert.equal(menu[0].path, '/dashboard');
    assert.equal(menu[0].onClick, onClick);
    assert.equal(menu[0].children, undefined);
});

test('overview preserves the allowed reports destination when no dashboard link was supplied', () => {
    const menu = buildRetailNavigation([{ key: 'retail-reports', path: '/reports' }]);
    assert.deepEqual(leaves(menu), ['retail-reports']);
    assert.equal(menu[0].path, '/reports');
    assert.equal(menu[0].label, 'Tổng quan');
});
