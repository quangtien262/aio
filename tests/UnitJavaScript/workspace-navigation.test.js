import assert from 'node:assert/strict';
import test from 'node:test';
import { currentWorkspaceNavigationItem, findWorkspaceMenuItem, moduleWorkspaceNavigation } from '../../resources/admin/src/shared/utils/workspaceNavigation.js';

const items = [
    { key: 'store', route: '/modules', source: 'static' },
    { key: 'fnb', route: '/modules/fnb-pos', source: 'module', moduleKey: 'fnb-pos' },
    { key: 'customers', route: '/modules/customers', source: 'module', moduleKey: 'customers' },
    { key: 'customer-data', route: '/modules/customers/data', source: 'module', moduleKey: 'customers' },
];

test('nested app routes match the most specific menu rather than the App Store prefix', () => {
    assert.equal(currentWorkspaceNavigationItem(items, '/modules/fnb-pos/dashboard').key, 'fnb');
    assert.equal(currentWorkspaceNavigationItem(items, '/modules/customers/data/result').key, 'customer-data');
    assert.equal(currentWorkspaceNavigationItem(items, '/modules').key, 'store');
    assert.equal(currentWorkspaceNavigationItem(items, '/modules/fnb-pos-other'), items[0]);
    assert.equal(currentWorkspaceNavigationItem(items, '/unknown'), null);
});

test('dynamic app menus never leak to another app, platform page, actor or website', () => {
    const menu = { moduleKey: 'fnb-pos', identity: 'website-main:2', items: [{ key: 'group', children: [{ key: 'staff', route: '/modules/fnb-pos/staff' }] }] };
    assert.equal(moduleWorkspaceNavigation(items[1], menu, 'website-main:2'), menu);
    assert.equal(moduleWorkspaceNavigation(items[2], menu, 'website-main:2'), null);
    assert.equal(moduleWorkspaceNavigation(items[0], menu, 'website-main:2'), null);
    assert.equal(moduleWorkspaceNavigation(items[1], menu, 'website-main:3'), null);
    assert.equal(moduleWorkspaceNavigation(items[1], menu, 'website-other:2'), null);
    assert.equal(moduleWorkspaceNavigation(null, menu, 'website-main:2'), null);
    assert.equal(findWorkspaceMenuItem(menu.items, 'staff').route, '/modules/fnb-pos/staff');
    assert.equal(findWorkspaceMenuItem(menu.items, 'missing'), null);
});
