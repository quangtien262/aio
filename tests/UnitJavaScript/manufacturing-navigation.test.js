import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { buildManufacturingNavigation } from '../../resources/admin/src/modules/manufacturing/utils/manufacturingNavigation.js';
import { findWorkspaceMenuItem } from '../../resources/admin/src/shared/utils/workspaceNavigation.js';

const manifest = JSON.parse(readFileSync(new URL('../../modules/Manufacturing/module.json', import.meta.url), 'utf8'));
const links = manifest.menus.map((item) => Object.freeze({ ...item, moduleKey: 'manufacturing' }));

test('all manufacturing destinations remain reachable exactly once after grouping', () => {
    const menu = buildManufacturingNavigation(links);
    assert.equal(menu.length, 5);
    const leaves = menu.flatMap((item) => item.children ?? [item]);
    assert.equal(leaves.length, links.length);
    assert.equal(new Set(leaves.map((item) => item.key)).size, links.length);
    for (const link of links) assert.equal(findWorkspaceMenuItem(menu, link.key), link);
    assert.equal(findWorkspaceMenuItem(menu, 'manufacturing-costing').permission, 'manufacturing.cost.view');
    assert.equal(findWorkspaceMenuItem(menu, 'manufacturing-settings').permission, 'manufacturing.settings');
});

test('restricted menus never restore denied links or show empty groups', () => {
    const permitted = links.filter((item) => ['manufacturing-overview', 'manufacturing-execution'].includes(item.key));
    const menu = buildManufacturingNavigation(permitted);
    assert.deepEqual(menu.map((item) => item.key), ['manufacturing-overview', 'manufacturing-production-group']);
    assert.deepEqual(menu[1].children, [permitted[1]]);
    assert.equal(findWorkspaceMenuItem(menu, 'manufacturing-costing'), null);
    assert.equal(findWorkspaceMenuItem(menu, 'manufacturing-settings'), null);
    assert.deepEqual(buildManufacturingNavigation([]), []);
});

test('future authorized destinations are preserved without changing their route', () => {
    const extra = { key: 'manufacturing-traceability', route: '/modules/manufacturing/traceability' };
    const menu = buildManufacturingNavigation([...links, extra]);
    assert.equal(findWorkspaceMenuItem(menu, extra.key), extra);
});
