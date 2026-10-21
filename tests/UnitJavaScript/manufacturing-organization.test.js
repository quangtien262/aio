import assert from 'node:assert/strict';
import test from 'node:test';
import { manufacturingCompanyOrganization, manufacturingOrganization, manufacturingOrganizationRows } from '../../resources/admin/src/modules/manufacturing/utils/manufacturingOrganization.js';

test('organization shows all supplied factories and keeps children within their factory', () => {
    const groups = manufacturingOrganization([{ id: 'a' }, { id: 'b' }, { id: 'empty' }], [
        { id: 'wa', factory_id: 'a' }, { id: 'wb', factory_id: 'b' }, { id: 'secret', factory_id: 'hidden' },
    ], [
        { id: 'la', factory_id: 'a', workshop_id: 'wa' }, { id: 'lb', factory_id: 'b', workshop_id: 'wb' },
        { id: 'unassigned', factory_id: 'b', workshop_id: null }, { id: 'cross', factory_id: 'b', workshop_id: 'wa' },
        { id: 'hidden-line', factory_id: 'hidden', workshop_id: 'secret' },
    ]);
    assert.deepEqual(groups.map((group) => group.factory.id), ['a', 'b', 'empty']);
    assert.deepEqual(groups[0].workshops[0].lines.map((line) => line.id), ['la']);
    assert.deepEqual(groups[1].workshops[0].lines.map((line) => line.id), ['lb']);
    assert.deepEqual(groups[1].otherLines.map((line) => line.id), ['unassigned', 'cross']);
    assert.deepEqual(groups[2].workshops, []);
    assert.deepEqual(groups[2].otherLines, []);
    assert.ok(!JSON.stringify(groups).includes('secret'));
    assert.ok(!JSON.stringify(groups).includes('hidden-line'));
});

test('flat organization rows keep every level and identify the parent and creation context', () => {
    const companies = [{ id: 'c', name: 'Doanh nghiệp', code: 'C1' }];
    const groups = manufacturingOrganization([{ id: 'f', company_id: 'c', name: 'Nhà máy', code: 'NM01' }], [{ id: 'w', factory_id: 'f', name: 'Phân xưởng', code: 'PX01' }], [
        { id: 'l', factory_id: 'f', workshop_id: 'w', name: 'Chuyền', code: 'CM01', capacity_minutes: '480' },
        { id: 'unassigned', factory_id: 'f', name: 'Chuyền chưa gắn', code: 'CM02' },
    ]);
    const rows = manufacturingOrganizationRows(companies, groups);
    assert.deepEqual(rows.map((row) => row.kind), ['company', 'factory', 'workshop', 'line', 'line']);
    assert.deepEqual(rows.map((row) => row.depth), [0, 1, 2, 3, 2]);
    assert.equal(new Set(rows.map((row) => row.key)).size, 5);
    assert.equal(rows[2].factoryId, 'f');
    assert.equal(rows[2].workshopId, 'w');
    assert.equal(rows[3].parent, 'NM01 / PX01 · Phân xưởng');
    assert.equal(rows[3].detail, '480');
    assert.equal(rows[4].parent, 'NM01 · Chưa gắn phân xưởng');
    assert.equal(manufacturingOrganizationRows(companies, groups, 'CM01').length, 5);
});

test('company hierarchy and accent-insensitive search preserve the matching factory context', () => {
    const companies = [{ id: 'c1', name: 'Công ty May' }, { id: 'c2', name: 'Công ty mới' }];
    const factories = manufacturingOrganization([
        { id: 'f1', company_id: 'c1', name: 'Nhà máy Bắc' },
        { id: 'f2', company_id: 'c1', name: 'Nhà máy Nam' },
        { id: 'hidden', company_id: 'not-visible', name: 'Hidden factory' },
    ], [{ id: 'w1', factory_id: 'f1', name: 'Phân xưởng Cắt' }], [
        { id: 'l1', factory_id: 'f1', workshop_id: 'w1', name: 'Chuyền số một' },
        { id: 'l2', factory_id: 'f1', workshop_id: 'w1', name: 'Chuyền số hai' },
    ]);
    const all = manufacturingCompanyOrganization(companies, factories);
    assert.equal(all.length, 2);
    assert.equal(all[0].factories.length, 2);
    assert.equal(all[1].factories.length, 0);
    const match = manufacturingCompanyOrganization(companies, factories, 'chuyen so mot');
    assert.equal(match.length, 1);
    assert.equal(match[0].company.id, 'c1');
    assert.equal(match[0].factories.length, 1);
    assert.equal(match[0].factories[0].factory.id, 'f1');
    assert.equal(match[0].factories[0].workshops[0].lines.length, 2);
    assert.equal(manufacturingCompanyOrganization(companies, factories, 'CONG TY MAY')[0].factories.length, 2);
    assert.deepEqual(manufacturingCompanyOrganization(companies, factories, 'not found'), []);
});
