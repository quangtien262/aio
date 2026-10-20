export const uuid = (value) => String(value?.public_id ?? value?.id ?? value ?? '');
export const rows = (value) => {
    if (Array.isArray(value)) return value;
    const candidate = value?.items ?? value?.data;
    return Array.isArray(candidate) ? candidate : [];
};
export const appendQuery = (path, params = {}) => {
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
    });
    return search.size ? `${path}${path.includes('?') ? '&' : '?'}${search}` : path;
};

export const minor = (value) => {
    const text = String(value ?? '0');
    if (!/^-?\d+$/.test(text)) throw new Error('Số tiền cần là số nguyên đồng.');
    return BigInt(text);
};

export const quantityMicros = (value) => {
    const text = String(value ?? '0');
    if (!/^\d+(?:\.\d{1,6})?$/.test(text)) throw new Error('Số lượng phải là số dương, tối đa 6 chữ số thập phân.');
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole) * 1000000n + BigInt(fraction.padEnd(6, '0'));
};

export const quantityText = (micros) => {
    const whole = micros / 1000000n;
    const fraction = String(micros % 1000000n).padStart(6, '0').replace(/0+$/, '');
    return fraction ? `${whole}.${fraction}` : String(whole);
};

export const addQuantity = (a, b = '1') => quantityText(quantityMicros(a) + quantityMicros(b));
export const estimateLine = (quantity, price) => (quantityMicros(quantity) * minor(price) + 500000n) / 1000000n;
export const formatMoney = (value) => {
    try { return `${minor(value).toLocaleString('vi-VN')} ₫`; } catch { return '—'; }
};
export const formatQuantity = (value) => String(value ?? '0').replace('.', ',');
export const formatDate = (value) => {
    if (!value) return '—';
    const text = String(value);
    const normalized = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(text) ? `${text.replace(' ', 'T')}Z` : value;
    const parsed = new Date(normalized);
    return Number.isNaN(parsed.valueOf()) ? String(value) : parsed.toLocaleString('vi-VN');
};
export const saleAmount = (sale) => String(sale?.total_minor ?? sale?.amount_due_minor ?? sale?.net_minor ?? sale?.grand_total_minor ?? '0');
export const statusLabels = {
    active: 'Đang hoạt động', inactive: 'Tạm ngưng', open: 'Đang mở', closed: 'Đã đóng', reconciled: 'Đã đối soát',
    draft: 'Bản nháp', priced: 'Đã tính giá', awaiting_payment: 'Chờ thanh toán', completed: 'Hoàn tất',
    cancelled: 'Đã hủy', approved: 'Đã duyệt', dispatched: 'Đang vận chuyển', partially_received: 'Đã nhận một phần',
    received: 'Đã nhận đủ', posted: 'Đã ghi nhận', counting: 'Đang kiểm đếm', confirmed: 'Đã xác nhận',
    pending: 'Đang xử lý', unknown: 'Chưa rõ kết quả', failed: 'Không thành công', quarantine: 'Cách ly',
};
