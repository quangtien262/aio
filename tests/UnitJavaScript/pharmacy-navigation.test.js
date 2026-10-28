import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { buildPharmacyNavigation } from '../../resources/admin/src/modules/pharmacy/utils/pharmacyNavigation.js';
import { findWorkspaceMenuItem } from '../../resources/admin/src/shared/utils/workspaceNavigation.js';

const manifest = JSON.parse(readFileSync(new URL('../../modules/Pharmacy/module.json', import.meta.url), 'utf8'));
const links = manifest.menus.map((item) => Object.freeze({ ...item, moduleKey: 'pharmacy' }));

test('pharmacy grouping preserves every authorized route and permission exactly once', () => {
    const menu = buildPharmacyNavigation(links);
    assert.equal(menu.length, 6);
    assert.deepEqual(menu.slice(0, 2).map((item) => item.key), ['pharmacy-work', 'pharmacy-pos']);
    const leaves = menu.flatMap((item) => item.children ?? [item]);
    assert.equal(leaves.length, links.length);
    assert.equal(new Set(leaves.map((item) => item.key)).size, links.length);
    for (const link of links) assert.equal(findWorkspaceMenuItem(menu, link.key), link);
});

test('report-only pharmacy navigation never restores clinical or settings destinations', () => {
    const allowed = links.filter((item) => item.key === 'pharmacy-reports');
    const menu = buildPharmacyNavigation(allowed);
    assert.equal(menu.length, 1);
    assert.deepEqual(menu[0].children, allowed);
    for (const key of ['pharmacy-work', 'pharmacy-pos', 'pharmacy-prescriptions', 'pharmacy-settings']) assert.equal(findWorkspaceMenuItem(menu, key), null);
    assert.deepEqual(buildPharmacyNavigation([]), []);
});

test('partial pharmacy grants omit empty groups and retain future authorized links', () => {
    const future = Object.freeze({ key: 'pharmacy-future', route: '/modules/pharmacy/future' });
    const allowed = links.filter((item) => ['pharmacy-products', 'pharmacy-inventory'].includes(item.key));
    const menu = buildPharmacyNavigation([...allowed, future]);
    assert.deepEqual(menu.map((item) => item.key), ['pharmacy-products-group', 'pharmacy-stock-group', 'pharmacy-future']);
    assert.equal(findWorkspaceMenuItem(menu, future.key), future);
});
