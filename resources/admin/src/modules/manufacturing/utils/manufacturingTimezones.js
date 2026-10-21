const preferred = [
    ['Asia/Ho_Chi_Minh', 'Việt Nam'],
    ['Asia/Bangkok', 'Thái Lan — Bangkok'],
    ['Asia/Singapore', 'Singapore'],
    ['Asia/Shanghai', 'Trung Quốc — Thượng Hải'],
    ['Asia/Taipei', 'Đài Loan — Đài Bắc'],
    ['Asia/Hong_Kong', 'Hồng Kông'],
    ['Asia/Tokyo', 'Nhật Bản — Tokyo'],
    ['Asia/Seoul', 'Hàn Quốc — Seoul'],
    ['UTC', 'Giờ quốc tế'],
    ['Europe/London', 'Anh — London'],
    ['Europe/Paris', 'Pháp — Paris'],
    ['America/New_York', 'Mỹ — New York'],
    ['America/Los_Angeles', 'Mỹ — Los Angeles'],
    ['Australia/Sydney', 'Úc — Sydney'],
];
const names = new Map(preferred);
export const timezoneSearchText = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().trim();

export function manufacturingTimezoneOption(value, instant = new Date()) {
    const name = names.get(value) ?? value.replaceAll('_', ' ');
    let offset = '';
    try {
        const part = new Intl.DateTimeFormat('en', { timeZone: value, timeZoneName: 'longOffset' }).formatToParts(instant).find((item) => item.type === 'timeZoneName')?.value;
        if (part) offset = part === 'GMT' ? 'UTC+00:00' : part.replace('GMT', 'UTC');
    } catch { /* Preserve an existing identifier even if this browser cannot format it. */ }
    const label = offset ? `${name} (${offset})` : name;
    return { value, label, searchText: timezoneSearchText(`${label} ${value}`) };
}

export function manufacturingTimezoneOptions(identifiers = [...names.keys()], instant = new Date()) {
    const supported = new Set(identifiers);
    return [...new Set([...names.keys()].filter((value) => supported.has(value)).concat(identifiers))]
        .map((value) => manufacturingTimezoneOption(value, instant));
}
