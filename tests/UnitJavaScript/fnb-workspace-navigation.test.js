import assert from 'node:assert/strict';
import test from 'node:test';
import { buildFnbWorkspaceNavigation, selectedFnbWorkspaceMenuKey } from '../../resources/admin/src/modules/fnb-pos/utils/fnbWorkspaceNavigation.js';

test('cashier navigation exposes only live permitted sections, without adding configuration actions', () => {
    const items = buildFnbWorkspaceNavigation([{ key: 'pos', label: 'Bán hàng' }], []);
    assert.deepEqual(items.map((item) => item.key), ['fnb-workspace-pos']);
    assert.equal(items[0].route, '/modules/fnb-pos/pos');
    assert.deepEqual(buildFnbWorkspaceNavigation([], []), []);
});

test('report-only roles receive the report destination and cannot select the unavailable today view', () => {
    const items = buildFnbWorkspaceNavigation([{ key: 'dashboard', label: 'Tổng quan' }], ['reports']);
    assert.equal(items[0].label, 'Báo cáo');
    assert.equal(items[0].route, '/modules/fnb-pos/dashboard?view=reports');
    assert.equal(items[0].children, undefined);
    assert.equal(selectedFnbWorkspaceMenuKey('dashboard', '?view=today', ['reports']), 'fnb-workspace-reports');
});

test('overview combines only the allowed views and configuration retains each nested destination', () => {
    const sections = [{ key: 'dashboard', label: 'Tổng quan' }, { key: 'pos', label: 'Bán hàng' }, { key: 'staff', label: 'Nhân viên' }, { key: 'guide', label: 'Hướng dẫn' }];
    const items = buildFnbWorkspaceNavigation(sections, ['today', 'reports']);
    assert.deepEqual(items[0].children.map((item) => item.route), ['/modules/fnb-pos/dashboard?view=today', '/modules/fnb-pos/dashboard?view=reports']);
    assert.deepEqual(items.at(-1).children.map((item) => item.route), ['/modules/fnb-pos/staff', '/modules/fnb-pos/guide']);
    assert.equal(items.at(-1).route, undefined);
    assert.equal(selectedFnbWorkspaceMenuKey('dashboard', '?view=reports', ['today', 'reports']), 'fnb-workspace-reports');
    assert.equal(selectedFnbWorkspaceMenuKey('dashboard', '?view=invalid', ['today']), 'fnb-workspace-today');
    assert.equal(selectedFnbWorkspaceMenuKey('staff', '?view=reports', ['today']), 'fnb-workspace-staff');
});
