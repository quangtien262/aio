export const customerId = (value) => String(value && typeof value === 'object' ? value.public_id ?? value.id ?? '' : value ?? '');
export const customerRows = (value) => Array.isArray(value) ? value : Array.isArray(value?.items) ? value.items : [];
export const customerDate = (value) => {
    if (!value) return '—';
    const text = String(value);
    const parsed = new Date(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(text) ? `${text.replace(' ', 'T')}Z` : text);
    return Number.isNaN(parsed.valueOf()) ? text : parsed.toLocaleString('vi-VN');
};
export const customerOption = (profile) => ({
    value: customerId(profile), name: profile.name, code: profile.code, phone: profile.phone ?? profile.phone_normalized,
    label: [profile.name, profile.code, profile.phone ?? profile.phone_normalized].filter(Boolean).join(' · '),
});
export function parseCustomerCsv(source) {
    const text = String(source).replace(/^\uFEFF/, '');
    const result = [];
    let row = [], field = '', quoted = false, closed = false;
    for (let i = 0; i < text.length; i += 1) {
        const c = text[i];
        if (quoted) {
            if (c === '"' && text[i + 1] === '"') { field += '"'; i += 1; }
            else if (c === '"') { quoted = false; closed = true; }
            else field += c;
        } else if (c === '"' && field === '' && !closed) quoted = true;
        else if (c === ',' || c === '\n' || c === '\r') {
            row.push(field); field = ''; closed = false;
            if (c !== ',') {
                if (row.some((value) => value !== '')) result.push(row);
                row = [];
                if (c === '\r' && text[i + 1] === '\n') i += 1;
            }
        } else {
            if (closed || c === '"') throw new Error('CSV có dấu nháy không hợp lệ. Hãy xuất lại file CSV UTF-8.');
            field += c;
        }
    }
    if (quoted) throw new Error('CSV chưa đóng dấu nháy.');
    row.push(field);
    if (row.some((value) => value !== '')) result.push(row);
    if (!result.length) throw new Error('File chưa có dữ liệu.');
    const [headers, ...records] = result;
    if (headers.some((header) => !header.trim()) || new Set(headers).size !== headers.length) throw new Error('Tiêu đề cột cần có tên và không được trùng.');
    if (records.some((record) => record.length !== headers.length)) throw new Error('Số cột của một số dòng không khớp tiêu đề.');
    if (records.length > 1000) throw new Error('Mỗi lần nhập tối đa 1.000 khách hàng.');
    return { headers, records };
}
export function mapCustomerImport(parsed, mapping) {
    if (!Object.values(mapping).includes('name')) throw new Error('Ánh xạ một cột vào Tên khách hàng.');
    const targets = Object.values(mapping).filter(Boolean);
    if (new Set(targets).size !== targets.length) throw new Error('Mỗi trường chỉ nhận dữ liệu từ một cột.');
    return parsed.records.map((record) => Object.fromEntries(parsed.headers.flatMap((header, index) => mapping[header] ? [[mapping[header], record[index].trim()]] : [])));
}
export function serializeCustomerProfile(values, { profile, fields = [], sensitiveVisible = true } = {}) {
    const allowed = ['name', 'type', 'code', 'legal_name', ...(sensitiveVisible ? ['tax_code', 'birthday', 'notes'] : [])];
    const payload = Object.fromEntries(allowed.filter((key) => values[key] !== undefined).map((key) => [key, values[key]]));
    payload.name = String(values.name ?? '').trim();
    if (profile) payload.expected_version = profile.version;
    if (sensitiveVisible) {
        payload.contacts = customerRows(values.contacts).filter((item) => item.value?.trim()).map((item) => ({ kind: item.kind, value: item.value.trim(), label: item.label, country_code: item.country_code || 'VN', is_primary: Boolean(item.is_primary) }));
        payload.addresses = customerRows(values.addresses).map((item) => ({ label: item.label, kind: item.kind, line1: item.line1, line2: item.line2, city: item.city, region: item.region, postal_code: item.postal_code, country_code: item.country_code || 'VN', is_default: Boolean(item.is_default) }));
    }
    payload.tags = customerRows(values.tags);
    payload.custom_fields = Object.fromEntries(Object.entries(values.custom_fields ?? {}).filter(([key]) => fields.some((field) => field.status !== 'archived' && (!field.sensitive || sensitiveVisible) && `${field.namespace ?? 'customers'}.${field.key}` === key)));
    return payload;
}
export function customerMergeFields(left, right) {
    const sensitive = Boolean(left?.sensitive_visible && right?.sensitive_visible);
    return [
        { key: 'name', label: 'Tên hiển thị' }, { key: 'legal_name', label: 'Tên pháp lý' },
        ...(sensitive ? [{ key: 'tax_code', label: 'Mã số thuế' }, { key: 'birthday', label: 'Ngày sinh' }, { key: 'notes', label: 'Ghi chú' }, { key: 'contacts', label: 'Liên hệ', multiple: true }, { key: 'addresses', label: 'Địa chỉ', multiple: true }] : []),
        { key: 'tags', label: 'Nhãn', multiple: true },
    ];
}
export function serializeCustomerMergeChoices(left, right, choices) {
    const fields = customerMergeFields(left, right);
    return Object.fromEntries(fields.filter((field) => Object.hasOwn(choices, field.key)).map((field) => {
        const selected = choices[field.key];
        if (!(field.multiple ? ['source', 'target', 'combine'] : ['source', 'target']).includes(selected)) throw new Error('Lựa chọn hợp nhất không hợp lệ.');
        return [field.key, selected];
    }));
}
export function customerMigrationActions(report) {
    const valid = report?.valid === true;
    return { canImport: valid && (!report.committed || report.has_more === true) && !report.cutover, canCutover: valid && report.has_more === false && !report.cutover };
}
export function customerFilterSnapshot(filters) {
    return { q: String(filters.q ?? '').trim(), status: ['active', 'archived', 'merged', 'all'].includes(filters.status) ? filters.status : 'active', type: ['person', 'company'].includes(filters.type) ? filters.type : null, tag: String(filters.tag ?? '').trim() || null, per_page: [10, 20, 50, 100].includes(Number(filters.per_page)) ? Number(filters.per_page) : 20 };
}
