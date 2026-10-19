import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fnbSectionPath, overviewPermissions, overviewView, overviewViews } from '../../resources/admin/src/modules/fnb-pos/utils/fnbOverviewNavigation.js';
const can = (permissions) => (permission) => permissions.includes(permission);

test('overview supports dashboard-only and report-only roles without granting other views', () => {
    assert.deepEqual(overviewViews(can(['fnb.dashboard.view'])), ['today']);
    assert.deepEqual(overviewViews(can(['fnb.report.operations.view'])), ['reports']);
    assert.deepEqual(overviewViews(can(['fnb.report.financial.view'])), ['reports']);
    assert.deepEqual(overviewViews(can(overviewPermissions)), ['today', 'reports']);
    assert.deepEqual(overviewViews(can([])), []);
});
test('unavailable views fall back to an authorized view', () => {
    assert.equal(overviewView('reports', ['today']), 'today');
    assert.equal(overviewView('today', ['reports']), 'reports');
    assert.equal(overviewView(null, ['today', 'reports']), 'today');
    assert.equal(overviewView('reports', ['today', 'reports']), 'reports');
    assert.equal(overviewView('reports', []), undefined);
});
test('report links resolve into the unified overview while other destinations retain their routes', () => {
    assert.equal(fnbSectionPath('reports'), '/modules/fnb-pos/dashboard?view=reports');
    assert.equal(fnbSectionPath('dashboard'), '/modules/fnb-pos/dashboard');
    assert.equal(fnbSectionPath('pos'), '/modules/fnb-pos/pos');
});
