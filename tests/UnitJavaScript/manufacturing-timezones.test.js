import assert from 'node:assert/strict';
import test from 'node:test';
import { manufacturingTimezoneOption, manufacturingTimezoneOptions, timezoneSearchText } from '../../resources/admin/src/modules/manufacturing/utils/manufacturingTimezones.js';

test('timezone labels preserve stored identifiers and prioritize Vietnam without duplicates', () => {
    const options = manufacturingTimezoneOptions(['UTC', 'Asia/Ho_Chi_Minh', 'Asia/Bangkok', 'Asia/Ho_Chi_Minh'], new Date('2026-01-15T12:00:00Z'));
    assert.equal(options[0].value, 'Asia/Ho_Chi_Minh');
    assert.equal(options[0].label, 'Việt Nam (UTC+07:00)');
    assert.equal(new Set(options.map((item) => item.value)).size, options.length);
    assert.ok(options.some((item) => item.value === 'UTC'));
    assert.equal(options.length, 3);
    assert.equal(manufacturingTimezoneOption('US/Eastern').value, 'US/Eastern');
});

test('displayed offsets follow the selected zone daylight saving time', () => {
    assert.match(manufacturingTimezoneOption('America/New_York', new Date('2026-01-15T12:00:00Z')).label, /UTC-05:00/);
    assert.match(manufacturingTimezoneOption('America/New_York', new Date('2026-07-15T12:00:00Z')).label, /UTC-04:00/);
    assert.match(manufacturingTimezoneOption('UTC').label, /UTC\+00:00/);
});

test('timezone search supports Vietnamese without accents and the stored IANA identifier', () => {
    const vietnam = manufacturingTimezoneOption('Asia/Ho_Chi_Minh');
    assert.ok(vietnam.searchText.includes(timezoneSearchText('viet nam')));
    assert.ok(vietnam.searchText.includes(timezoneSearchText('Asia/Ho_Chi_Minh')));
    assert.ok(manufacturingTimezoneOption('Asia/Bangkok').searchText.includes(timezoneSearchText('thai lan')));
});
