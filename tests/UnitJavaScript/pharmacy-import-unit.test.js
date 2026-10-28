import assert from 'node:assert/strict';
import test from 'node:test';
import { parseProductImport } from '../../resources/admin/src/modules/pharmacy/utils/pharmacyImport.js';

test('CSV preserves quoted names, multiline text and integer money strings', () => {
    const [row] = parseProductImport('\uFEFFMã hàng,Tên hàng,Đơn vị,Giá bán,Hoạt chất,Bán theo đơn\r\nA,"Tên, có dấu ""kép""",vien,90071992547409,"Dòng một\nDòng hai",Có\r\n');
    assert.deepEqual(row, { code: 'A', name: 'Tên, có dấu "kép"', base_unit: 'vien', price_minor: '90071992547409', ingredients: 'Dòng một\nDòng hai', requires_prescription: true });
});
test('spreadsheet paste recognizes tab separators and explicit classification flags', () => {
    const rows = parseProductImport('Mã hàng\tTên hàng\tĐơn vị\tGiá bán\tKiểm soát đặc biệt\tChuỗi lạnh\nA\tTên mẫu\thop\t1000\tKhông\ttrue');
    assert.equal(rows[0].controlled, false);
    assert.equal(rows[0].cold_chain, true);
});
test('ambiguous or oversized imports stop before creating a server preview', () => {
    for (const text of ['code,name,base_unit,price_minor,code\nA,A,vien,1000,A', 'Mã hàng,Tên hàng,Đơn vị,Giá bán\nA,A,vien', 'Mã hàng,Tên hàng,Đơn vị,Giá bán\nA,"Chưa đóng,vien,1000', 'Mã hàng,Tên hàng,Đơn vị,Giá bán,Bán theo đơn\nA,A,vien,1000,có thể']) assert.throws(() => parseProductImport(text));
    assert.throws(() => parseProductImport('Mã hàng,Tên hàng,Đơn vị,Giá bán\n' + Array.from({ length: 101 }, (_, i) => `${i},A,vien,1000`).join('\n')), /100/);
});
